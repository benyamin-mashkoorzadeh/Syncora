<?php

namespace Tests\Feature\Task;

use App\Actions\Notifications\CreateUserNotification;
use App\Actions\Tasks\AddTaskComment;
use App\Actions\Tasks\DeleteTaskComment;
use App\Actions\Tasks\RecordTaskActivity;
use App\Actions\Tasks\UpdateTask;
use App\Actions\Tasks\UpdateTaskComment;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class TaskCollaborationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_workspace_member_can_add_comment_with_authenticated_author_and_activity(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);
        $member = $this->addUser($workspace, WorkspaceRole::Member);
        $outsider = User::factory()->create();
        $task = Task::factory()->for($project)->create();

        $response = $this->actingAs($member)->postJson($this->commentsUrl($workspace, $project, $task), [
            'body' => '  This decision is ready for review.  ',
            'author_id' => $outsider->id,
        ], $this->spaHeaders())->assertCreated()
            ->assertJsonPath('data.body', 'This decision is ready for review.')
            ->assertJsonPath('data.author.id', $member->id)
            ->assertJsonPath('data.author.name', $member->name)
            ->assertJsonMissingPath('data.author.email')
            ->assertJsonMissingPath('data.task_id')
            ->assertJsonMissingPath('data.author_id');

        $commentId = $response->json('data.id');
        $this->assertDatabaseHas('task_comments', [
            'id' => $commentId,
            'task_id' => $task->id,
            'author_id' => $member->id,
        ]);
        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'actor_id' => $member->id,
            'action' => 'comment.added',
        ]);
        $this->assertSame($commentId, TaskActivity::firstOrFail()->metadata['comment_id']);
    }

    public function test_comment_validation_and_paginated_retrieval_are_safe_and_consistent(): void
    {
        [$workspace, $project, $member] = $this->projectWithUser(WorkspaceRole::Member);
        $task = Task::factory()->for($project)->create();
        $older = TaskComment::factory()->for($task)->for($member, 'author')->create(['body' => 'Older']);
        $newer = TaskComment::factory()->for($task)->for($member, 'author')->create(['body' => 'Newer']);

        $this->actingAs($member)->postJson($this->commentsUrl($workspace, $project, $task), [
            'body' => '   ',
        ], $this->spaHeaders())->assertUnprocessable()->assertJsonValidationErrors('body');

        $this->actingAs($member)->getJson($this->commentsUrl($workspace, $project, $task), $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonMissingPath('data.0.author.email')
            ->assertJsonMissingPath('data.0.author_id');
    }

    public function test_comment_author_can_edit_own_comment_with_safe_activity_and_no_duplicate_for_a_no_op(): void
    {
        [$workspace, $project, $author] = $this->projectWithUser(WorkspaceRole::Member);
        $task = Task::factory()->for($project)->create();
        $comment = TaskComment::factory()->for($task)->for($author, 'author')->create([
            'body' => 'Original comment body.',
        ]);
        $originalCreatedAt = $comment->created_at->toISOString();
        $this->travel(1)->second();

        $this->actingAs($author)->patchJson($this->commentUrl($workspace, $project, $task, $comment), [
            'body' => '  Revised comment body.  ',
            'author_id' => User::factory()->create()->id,
        ], $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.body', 'Revised comment body.')
            ->assertJsonPath('data.author.id', $author->id)
            ->assertJsonPath('data.created_at', $originalCreatedAt)
            ->assertJsonMissingPath('data.author.email')
            ->assertJsonMissingPath('data.author_id')
            ->assertJsonMissingPath('data.deleted_at');

        $comment->refresh();
        $this->assertSame('Revised comment body.', $comment->body);
        $this->assertTrue($comment->updated_at->greaterThan($comment->created_at));

        $activity = TaskActivity::where('task_id', $task->id)
            ->where('action', 'comment.edited')
            ->sole();
        $this->assertSame($author->id, $activity->actor_id);
        $this->assertSame(['comment_id' => $comment->id], $activity->metadata);
        $this->assertStringNotContainsString('Original comment body.', json_encode($activity->metadata));
        $this->assertStringNotContainsString('Revised comment body.', json_encode($activity->metadata));

        $this->actingAs($author)->patchJson($this->commentUrl($workspace, $project, $task, $comment), [
            'body' => 'Revised comment body.',
        ], $this->spaHeaders())->assertOk();

        $this->assertSame(1, TaskActivity::where('task_id', $task->id)->where('action', 'comment.edited')->count());
    }

    public function test_other_workspace_member_cannot_edit_or_delete_a_comment(): void
    {
        [$workspace, $project, $author] = $this->projectWithUser(WorkspaceRole::Member);
        $otherMember = $this->addUser($workspace, WorkspaceRole::Member);
        $task = Task::factory()->for($project)->create();
        $comment = TaskComment::factory()->for($task)->for($author, 'author')->create([
            'body' => 'Only my author may change this.',
        ]);

        $this->actingAs($otherMember)->patchJson($this->commentUrl($workspace, $project, $task, $comment), [
            'body' => 'Unauthorized edit.',
        ], $this->spaHeaders())->assertForbidden();
        $this->actingAs($otherMember)->deleteJson(
            $this->commentUrl($workspace, $project, $task, $comment),
            [],
            $this->spaHeaders(),
        )->assertForbidden();

        $this->assertSame('Only my author may change this.', $comment->refresh()->body);
        $this->assertNull($comment->deleted_at);
        $this->assertDatabaseCount('task_activities', 0);
    }

    public function test_comment_update_validates_body_and_enforces_nested_task_scope(): void
    {
        [$workspace, $project, $author] = $this->projectWithUser(WorkspaceRole::Member);
        $task = Task::factory()->for($project)->create();
        $otherTask = Task::factory()->for($project)->create();
        $comment = TaskComment::factory()->for($task)->for($author, 'author')->create();

        $this->actingAs($author)->patchJson($this->commentUrl($workspace, $project, $task, $comment), [
            'body' => '   ',
        ], $this->spaHeaders())->assertUnprocessable()->assertJsonValidationErrors('body');

        $this->actingAs($author)->patchJson($this->commentUrl($workspace, $project, $otherTask, $comment), [
            'body' => 'Must not cross task boundaries.',
        ], $this->spaHeaders())->assertNotFound();
        $this->actingAs($author)->deleteJson(
            $this->commentUrl($workspace, $project, $otherTask, $comment),
            [],
            $this->spaHeaders(),
        )->assertNotFound();
    }

    public function test_comment_author_can_soft_delete_and_deleted_content_is_not_exposed(): void
    {
        [$workspace, $project, $author] = $this->projectWithUser(WorkspaceRole::Member);
        $task = Task::factory()->for($project)->create();
        $comment = TaskComment::factory()->for($task)->for($author, 'author')->create([
            'body' => 'Sensitive deleted comment text.',
        ]);

        $this->actingAs($author)->deleteJson(
            $this->commentUrl($workspace, $project, $task, $comment),
            [],
            $this->spaHeaders(),
        )->assertOk()->assertJsonPath('message', 'Comment deleted.');

        $this->assertSoftDeleted('task_comments', ['id' => $comment->id]);
        $this->actingAs($author)->getJson($this->commentsUrl($workspace, $project, $task), $this->spaHeaders())
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonMissing(['body' => 'Sensitive deleted comment text.']);

        $activity = TaskActivity::where('task_id', $task->id)
            ->where('action', 'comment.deleted')
            ->sole();
        $this->assertSame($author->id, $activity->actor_id);
        $this->assertSame(['comment_id' => $comment->id], $activity->metadata);
        $this->assertStringNotContainsString('Sensitive deleted comment text.', json_encode($activity->metadata));

        $this->actingAs($author)->deleteJson(
            $this->commentUrl($workspace, $project, $task, $comment),
            [],
            $this->spaHeaders(),
        )->assertNotFound();
        $this->assertSame(1, TaskActivity::where('task_id', $task->id)->where('action', 'comment.deleted')->count());
    }

    public function test_comment_and_activity_routes_enforce_authentication_verification_and_nested_isolation(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);
        $otherProject = Project::factory()->for($workspace)->create();
        $otherWorkspace = Workspace::factory()->create();
        $task = Task::factory()->for($project)->create();
        $outsider = User::factory()->create();
        $unverified = User::factory()->unverified()->create();
        WorkspaceMembership::factory()->for($workspace)->for($unverified)->create();

        $this->getJson($this->commentsUrl($workspace, $project, $task), $this->spaHeaders())->assertUnauthorized();
        $this->actingAs($unverified)->getJson($this->commentsUrl($workspace, $project, $task), $this->spaHeaders())->assertForbidden();
        $this->actingAs($outsider)->getJson($this->activitiesUrl($workspace, $project, $task), $this->spaHeaders())->assertNotFound();
        $this->actingAs($owner)->getJson($this->commentsUrl($workspace, $otherProject, $task), $this->spaHeaders())->assertNotFound();
        $this->actingAs($owner)->getJson($this->activitiesUrl($otherWorkspace, $project, $task), $this->spaHeaders())->assertNotFound();
    }

    public function test_task_creation_and_meaningful_updates_record_structured_activity_once_per_change(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);
        $assignee = $this->addUser($workspace, WorkspaceRole::Member);
        $sprint = Sprint::factory()->for($project)->create(['name' => 'Launch sprint']);

        $response = $this->actingAs($owner)->postJson($this->tasksUrl($workspace, $project), [
            'title' => 'Prepare launch',
            'description' => 'Initial scope.',
            'status' => TaskStatus::Todo->value,
            'priority' => TaskPriority::High->value,
        ], $this->spaHeaders())->assertCreated();
        $task = Task::findOrFail($response->json('data.id'));

        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'actor_id' => $owner->id,
            'action' => 'task.created',
        ]);

        $this->actingAs($owner)->patchJson($this->taskUrl($workspace, $project, $task), [
            'status' => TaskStatus::InProgress->value,
            'position' => 0,
            'priority' => TaskPriority::Urgent->value,
            'assignee_id' => $assignee->id,
            'sprint_id' => $sprint->id,
        ], $this->spaHeaders())->assertOk();

        $activities = $task->activities()->where('action', '!=', 'task.created')->orderBy('id')->get();
        $this->assertSame([
            'task.status_changed',
            'task.priority_changed',
            'task.assignee_changed',
            'task.sprint_changed',
        ], $activities->pluck('action')->all());
        $this->assertSame($owner->id, $activities->first()->actor_id);
        $this->assertSame('todo', $activities->first()->metadata['from']['value']);
        $this->assertSame('To Do', $activities->first()->metadata['from']['label']);
        $this->assertSame('in_progress', $activities->first()->metadata['to']['value']);
        $this->assertSame($assignee->name, $activities->firstWhere('action', 'task.assignee_changed')->metadata['to']['label']);
        $this->assertSame('Launch sprint', $activities->firstWhere('action', 'task.sprint_changed')->metadata['to']['label']);
        $this->assertSame(1, $activities->where('action', 'task.status_changed')->count());
    }

    public function test_unchanged_updates_and_position_only_reorders_do_not_create_activity(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);
        $first = Task::factory()->for($project)->create(['position' => 0]);
        $second = Task::factory()->for($project)->create(['position' => 1]);

        $this->actingAs($owner)->patchJson($this->taskUrl($workspace, $project, $first), [
            'title' => $first->title,
            'status' => $first->status->value,
            'priority' => $first->priority->value,
        ], $this->spaHeaders())->assertOk();
        $this->actingAs($owner)->patchJson($this->taskUrl($workspace, $project, $second), [
            'position' => 0,
        ], $this->spaHeaders())->assertOk();

        $this->assertDatabaseCount('task_activities', 0);
    }

    public function test_comment_and_activity_are_atomic_when_activity_recording_fails(): void
    {
        [$workspace, $project, $member] = $this->projectWithUser(WorkspaceRole::Member);
        $task = Task::factory()->for($project)->create();
        $recorder = Mockery::mock(RecordTaskActivity::class);
        $recorder->shouldReceive('record')->once()->andThrow(new RuntimeException('Activity write failed.'));

        try {
            (new AddTaskComment($recorder, app(CreateUserNotification::class)))->handle($task, $member, 'This must roll back.');
            $this->fail('The activity failure should escape the transaction.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Activity write failed.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('task_comments', ['task_id' => $task->id]);
        $this->assertDatabaseMissing('task_activities', ['task_id' => $task->id]);
    }

    public function test_comment_edit_and_delete_roll_back_when_activity_recording_fails(): void
    {
        [$workspace, $project, $author] = $this->projectWithUser(WorkspaceRole::Member);
        $task = Task::factory()->for($project)->create();
        $editable = TaskComment::factory()->for($task)->for($author, 'author')->create([
            'body' => 'Keep this body.',
        ]);
        $deletable = TaskComment::factory()->for($task)->for($author, 'author')->create();

        $editRecorder = Mockery::mock(RecordTaskActivity::class);
        $editRecorder->shouldReceive('record')->once()->andThrow(new RuntimeException('Edit activity failed.'));

        try {
            (new UpdateTaskComment($editRecorder))->handle($task, $editable, $author, 'Changed body.');
            $this->fail('The activity failure should roll back the comment edit.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Edit activity failed.', $exception->getMessage());
        }

        $this->assertSame('Keep this body.', $editable->refresh()->body);

        $deleteRecorder = Mockery::mock(RecordTaskActivity::class);
        $deleteRecorder->shouldReceive('record')->once()->andThrow(new RuntimeException('Delete activity failed.'));

        try {
            (new DeleteTaskComment($deleteRecorder))->handle($task, $deletable, $author);
            $this->fail('The activity failure should roll back the comment deletion.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Delete activity failed.', $exception->getMessage());
        }

        $this->assertNull($deletable->refresh()->deleted_at);
        $this->assertDatabaseMissing('task_activities', ['task_id' => $task->id]);
    }

    public function test_task_detail_and_activity_resources_expose_only_deliberate_fields(): void
    {
        [$workspace, $project, $member] = $this->projectWithUser(WorkspaceRole::Member);
        $task = Task::factory()->for($project)->create();
        TaskActivity::factory()->for($task)->for($member, 'actor')->create([
            'action' => 'task.priority_changed',
            'metadata' => ['from' => ['value' => 'low'], 'to' => ['value' => 'high']],
        ]);

        $this->actingAs($member)->getJson($this->taskUrl($workspace, $project, $task), $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.project.id', $project->id)
            ->assertJsonPath('data.project.name', $project->name)
            ->assertJsonMissingPath('data.project.workspace_id');

        $this->actingAs($member)->getJson($this->activitiesUrl($workspace, $project, $task), $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.action', 'task.priority_changed')
            ->assertJsonPath('data.0.actor.id', $member->id)
            ->assertJsonPath('data.0.metadata.from.value', 'low')
            ->assertJsonMissingPath('data.0.actor.email')
            ->assertJsonMissingPath('data.0.task_id')
            ->assertJsonPath('meta.per_page', 20);
    }

    public function test_comment_and_activity_collections_paginate_growing_history(): void
    {
        [$workspace, $project, $member] = $this->projectWithUser(WorkspaceRole::Member);
        $task = Task::factory()->for($project)->create();
        TaskComment::factory()->count(21)->for($task)->for($member, 'author')->create();
        TaskActivity::factory()->count(21)->for($task)->for($member, 'actor')->create();

        $this->actingAs($member)->getJson($this->commentsUrl($workspace, $project, $task), $this->spaHeaders())
            ->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('meta.total', 21)->assertJsonPath('meta.last_page', 2);
        $this->actingAs($member)->getJson($this->commentsUrl($workspace, $project, $task).'?page=2', $this->spaHeaders())
            ->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($member)->getJson($this->activitiesUrl($workspace, $project, $task), $this->spaHeaders())
            ->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('meta.total', 21)->assertJsonPath('meta.last_page', 2);
    }

    public function test_task_update_rolls_back_when_activity_recording_fails(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);
        $task = Task::factory()->for($project)->create(['priority' => TaskPriority::Medium]);
        $realRecorder = new RecordTaskActivity;
        $recorder = Mockery::mock(RecordTaskActivity::class);
        $recorder->shouldReceive('snapshot')->twice()->andReturnUsing(
            fn (Task $current): array => $realRecorder->snapshot($current),
        );
        $recorder->shouldReceive('recordChanges')->once()->andThrow(new RuntimeException('Activity write failed.'));

        try {
            (new UpdateTask($recorder, app(CreateUserNotification::class)))->handle($project, $task, $owner, ['priority' => TaskPriority::Urgent->value]);
            $this->fail('The activity failure should roll back the task update.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Activity write failed.', $exception->getMessage());
        }

        $this->assertSame(TaskPriority::Medium, $task->refresh()->priority);
        $this->assertDatabaseMissing('task_activities', ['task_id' => $task->id]);
    }

    /** @return array{Workspace, Project, User} */
    private function projectWithUser(WorkspaceRole $role): array
    {
        $workspace = Workspace::factory()->create();
        $project = Project::factory()->for($workspace)->create();
        $user = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($user)->create(['role' => $role]);

        return [$workspace, $project, $user];
    }

    private function addUser(Workspace $workspace, WorkspaceRole $role): User
    {
        $user = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($user)->create(['role' => $role]);

        return $user;
    }

    private function tasksUrl(Workspace $workspace, Project $project): string
    {
        return "/api/v1/workspaces/{$workspace->slug}/projects/{$project->slug}/tasks";
    }

    private function taskUrl(Workspace $workspace, Project $project, Task $task): string
    {
        return $this->tasksUrl($workspace, $project)."/{$task->id}";
    }

    private function commentsUrl(Workspace $workspace, Project $project, Task $task): string
    {
        return $this->taskUrl($workspace, $project, $task).'/comments';
    }

    private function commentUrl(Workspace $workspace, Project $project, Task $task, TaskComment $comment): string
    {
        return $this->commentsUrl($workspace, $project, $task)."/{$comment->id}";
    }

    private function activitiesUrl(Workspace $workspace, Project $project, Task $task): string
    {
        return $this->taskUrl($workspace, $project, $task).'/activities';
    }
}
