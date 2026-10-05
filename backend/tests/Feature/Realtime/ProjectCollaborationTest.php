<?php

namespace Tests\Feature\Realtime;

use App\Actions\Notifications\CreateUserNotification;
use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\RecordTaskActivity;
use App\Actions\Tasks\UpdateTask;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\WorkspaceRole;
use App\Events\ProjectBoardChanged;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProjectCollaborationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_project_presence_channel_authorizes_only_verified_workspace_members_with_safe_identity(): void
    {
        [$workspace, $project, $member] = $this->projectWithUser();
        $outsider = User::factory()->create();
        $unverified = User::factory()->unverified()->create();
        WorkspaceMembership::factory()->for($workspace)->for($unverified)->create(['role' => WorkspaceRole::Member]);
        $otherWorkspace = Workspace::factory()->create();
        $otherProject = Project::factory()->for($otherWorkspace)->create();

        $this->useReverbBroadcaster();

        $this->postJson('/broadcasting/auth', $this->channelPayload($project), $this->spaHeaders())
            ->assertForbidden();
        $this->actingAs($outsider)->postJson('/broadcasting/auth', $this->channelPayload($project), $this->spaHeaders())
            ->assertForbidden();
        $this->actingAs($unverified)->postJson('/broadcasting/auth', $this->channelPayload($project), $this->spaHeaders())
            ->assertForbidden();
        $this->actingAs($member)->postJson('/broadcasting/auth', $this->channelPayload($otherProject), $this->spaHeaders())
            ->assertForbidden();

        $response = $this->actingAs($member)
            ->postJson('/broadcasting/auth', $this->channelPayload($project), $this->spaHeaders())
            ->assertOk()
            ->assertJsonStructure(['auth', 'channel_data']);

        $presence = json_decode($response->json('channel_data'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame((string) $member->id, (string) $presence['user_id']);
        $this->assertSame([
            'id' => $member->id,
            'name' => $member->name,
            'avatar_url' => null,
        ], $presence['user_info']);
        $this->assertArrayNotHasKey('email', $presence['user_info']);
    }

    public function test_task_creation_and_meaningful_updates_dispatch_one_safe_board_event(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);
        Event::fake([ProjectBoardChanged::class]);

        $task = app(CreateTask::class)->handle($project, $owner, [
            'title' => 'Broadcast-safe task',
            'priority' => TaskPriority::Medium->value,
            'status' => TaskStatus::Todo->value,
        ]);

        Event::assertDispatched(ProjectBoardChanged::class, 1);
        Event::assertDispatched(ProjectBoardChanged::class, fn (ProjectBoardChanged $event): bool => $event->projectId === $project->id
            && $event->taskId === $task->id
            && $event->change === 'created'
            && $event->broadcastWith() === [
                'project_id' => $project->id,
                'task_id' => $task->id,
                'change' => 'created',
            ]);

        Event::fake([ProjectBoardChanged::class]);
        app(UpdateTask::class)->handle($project, $task, $owner, [
            'status' => TaskStatus::InProgress->value,
            'position' => 0,
            'priority' => TaskPriority::High->value,
        ]);

        Event::assertDispatched(ProjectBoardChanged::class, 1);
        Event::assertDispatched(ProjectBoardChanged::class, fn (ProjectBoardChanged $event): bool => $event->projectId === $project->id
            && $event->taskId === $task->id
            && $event->change === 'updated');

        $event = new ProjectBoardChanged($project->id, $task->id, 'updated');
        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, $event);
        $this->assertInstanceOf(ShouldRescue::class, $event);
        $this->assertInstanceOf(PresenceChannel::class, $event->broadcastOn());
        $this->assertSame("presence-project.{$project->id}.collaboration", $event->broadcastOn()->name);
        $this->assertSame(['project_id', 'task_id', 'change'], array_keys($event->broadcastWith()));
    }

    public function test_same_column_reorder_dispatches_once_and_preserves_authoritative_order(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);
        $first = Task::factory()->for($project)->create(['status' => TaskStatus::Todo, 'position' => 0]);
        $second = Task::factory()->for($project)->create(['status' => TaskStatus::Todo, 'position' => 1]);
        $third = Task::factory()->for($project)->create(['status' => TaskStatus::Todo, 'position' => 2]);
        Event::fake([ProjectBoardChanged::class]);

        app(UpdateTask::class)->handle($project, $third, $owner, ['position' => 0]);

        Event::assertDispatched(ProjectBoardChanged::class, 1);
        $this->assertSame(
            [$third->id, $first->id, $second->id],
            $project->tasks()->where('status', TaskStatus::Todo)->orderBy('position')->pluck('id')->all(),
        );
        $this->assertSame([0, 1, 2], $project->tasks()->where('status', TaskStatus::Todo)->orderBy('position')->pluck('position')->all());
    }

    public function test_unchanged_task_update_does_not_dispatch_board_event(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);
        $task = Task::factory()->for($project)->create();
        Event::fake([ProjectBoardChanged::class]);

        app(UpdateTask::class)->handle($project, $task, $owner, [
            'title' => $task->title,
            'status' => $task->status->value,
            'position' => $task->position,
            'priority' => $task->priority->value,
        ]);

        Event::assertNotDispatched(ProjectBoardChanged::class);
    }

    public function test_board_event_is_not_emitted_when_task_transaction_rolls_back(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);
        $task = Task::factory()->for($project)->create(['priority' => TaskPriority::Medium]);
        $recorder = Mockery::mock(RecordTaskActivity::class);
        $realRecorder = new RecordTaskActivity;
        $recorder->shouldReceive('snapshot')->twice()->andReturnUsing(
            fn (Task $current): array => $realRecorder->snapshot($current),
        );
        $recorder->shouldReceive('recordChanges')->once()->andThrow(new RuntimeException('Rollback requested.'));
        Event::fake([ProjectBoardChanged::class]);

        try {
            DB::transaction(fn () => (new UpdateTask($recorder, app(CreateUserNotification::class)))->handle($project, $task, $owner, [
                'priority' => TaskPriority::Urgent->value,
            ]));
            $this->fail('The task transaction should roll back.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Rollback requested.', $exception->getMessage());
        }

        $this->assertSame(TaskPriority::Medium, $task->refresh()->priority);
        Event::assertNotDispatched(ProjectBoardChanged::class);
    }

    /** @return array{Workspace, Project, User} */
    private function projectWithUser(WorkspaceRole $role = WorkspaceRole::Member): array
    {
        $workspace = Workspace::factory()->create();
        $project = Project::factory()->for($workspace)->create();
        $user = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($user)->create(['role' => $role]);

        return [$workspace, $project, $user];
    }

    /** @return array{socket_id: string, channel_name: string} */
    private function channelPayload(Project $project): array
    {
        return [
            'socket_id' => '1234.5678',
            'channel_name' => "presence-project.{$project->id}.collaboration",
        ];
    }

    private function useReverbBroadcaster(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        app(BroadcastManager::class)->forgetDrivers();
        require base_path('routes/channels.php');
    }
}
