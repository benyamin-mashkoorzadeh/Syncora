<?php

namespace Tests\Feature\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_can_create_task_with_server_scoped_relationships_and_safe_resource(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);
        $assignee = $this->addUser($workspace, WorkspaceRole::Member);
        $sprint = Sprint::factory()->for($project)->active()->create();
        $otherProject = Project::factory()->for($workspace)->create();

        $this->actingAs($owner)->postJson($this->tasksUrl($workspace, $project), [
            ...$this->taskPayload(),
            'sprint_id' => $sprint->id,
            'assignee_id' => $assignee->id,
            'project_id' => $otherProject->id,
            'position' => 99,
        ], $this->spaHeaders())->assertCreated()
            ->assertJsonPath('data.title', 'Ship project board')
            ->assertJsonPath('data.status', 'todo')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.position', 0)
            ->assertJsonPath('data.sprint.id', $sprint->id)
            ->assertJsonPath('data.assignee.id', $assignee->id)
            ->assertJsonMissingPath('data.project_id')
            ->assertJsonMissingPath('data.sprint_id')
            ->assertJsonMissingPath('data.assignee.email');

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'sprint_id' => $sprint->id,
            'assignee_id' => $assignee->id,
            'position' => 0,
        ]);
    }

    public function test_task_creation_validates_fields_enums_and_allows_no_sprint_or_assignee(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);

        $this->actingAs($owner)->postJson($this->tasksUrl($workspace, $project), [
            'title' => '',
            'description' => str_repeat('x', 5001),
            'status' => 'blocked',
            'priority' => 'critical',
        ], $this->spaHeaders())->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'description', 'status', 'priority']);

        $this->actingAs($owner)->postJson($this->tasksUrl($workspace, $project), $this->taskPayload([
            'sprint_id' => null,
            'assignee_id' => null,
        ]), $this->spaHeaders())->assertCreated()
            ->assertJsonPath('data.sprint', null)
            ->assertJsonPath('data.assignee', null);
    }

    public function test_owner_can_update_task_fields_status_sprint_and_assignee(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);
        $assignee = $this->addUser($workspace, WorkspaceRole::Member);
        $sprint = Sprint::factory()->for($project)->create();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($owner)->patchJson($this->taskUrl($workspace, $project, $task), [
            'title' => 'Updated task',
            'description' => 'Updated details.',
            'status' => 'in_progress',
            'priority' => 'urgent',
            'sprint_id' => $sprint->id,
            'assignee_id' => $assignee->id,
            'position' => 0,
        ], $this->spaHeaders())->assertOk()
            ->assertJsonPath('data.title', 'Updated task')
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.priority', 'urgent')
            ->assertJsonPath('data.sprint.id', $sprint->id)
            ->assertJsonPath('data.assignee.id', $assignee->id);

        $this->actingAs($owner)->patchJson($this->taskUrl($workspace, $project, $task), [
            'sprint_id' => null,
            'assignee_id' => null,
        ], $this->spaHeaders())->assertOk()
            ->assertJsonPath('data.sprint', null)
            ->assertJsonPath('data.assignee', null);
    }

    public function test_cross_project_sprint_and_non_member_assignee_are_rejected(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);
        $otherProject = Project::factory()->for($workspace)->create();
        $otherSprint = Sprint::factory()->for($otherProject)->create();
        $outsider = User::factory()->create();

        $this->actingAs($owner)->postJson($this->tasksUrl($workspace, $project), $this->taskPayload([
            'sprint_id' => $otherSprint->id,
            'assignee_id' => $outsider->id,
        ]), $this->spaHeaders())->assertUnprocessable()
            ->assertJsonValidationErrors(['sprint_id', 'assignee_id']);
    }

    public function test_tasks_append_reorder_and_move_between_columns_persist(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);
        $first = Task::factory()->for($project)->create(['title' => 'First', 'position' => 0]);
        $second = Task::factory()->for($project)->create(['title' => 'Second', 'position' => 1]);
        $third = Task::factory()->for($project)->create(['title' => 'Third', 'position' => 2]);
        $done = Task::factory()->for($project)->create(['title' => 'Done', 'status' => TaskStatus::Done, 'position' => 0]);

        $this->actingAs($owner)->patchJson($this->taskUrl($workspace, $project, $third), [
            'position' => 0,
        ], $this->spaHeaders())->assertOk()->assertJsonPath('data.position', 0);

        $this->assertSame(
            [$third->id, $first->id, $second->id],
            $project->tasks()->where('status', TaskStatus::Todo)->orderBy('position')->pluck('id')->all(),
        );

        $this->actingAs($owner)->patchJson($this->taskUrl($workspace, $project, $first), [
            'status' => 'done',
            'position' => 0,
        ], $this->spaHeaders())->assertOk()
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.position', 0);

        $this->assertSame(
            [$first->id, $done->id],
            $project->tasks()->where('status', TaskStatus::Done)->orderBy('position')->pluck('id')->all(),
        );
        $this->assertSame([0, 1], $project->tasks()->where('status', TaskStatus::Done)->orderBy('position')->pluck('position')->all());

        $response = $this->actingAs($owner)->getJson($this->tasksUrl($workspace, $project), $this->spaHeaders())->assertOk();
        $doneIds = collect($response->json('data'))->where('status', 'done')->pluck('id')->values()->all();
        $this->assertSame([$first->id, $done->id], $doneIds);
    }

    public function test_task_can_move_through_all_kanban_statuses(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);
        $task = Task::factory()->for($project)->create();

        foreach ([TaskStatus::InProgress, TaskStatus::Done, TaskStatus::Todo] as $status) {
            $this->actingAs($owner)->patchJson($this->taskUrl($workspace, $project, $task), [
                'status' => $status->value,
                'position' => 0,
            ], $this->spaHeaders())->assertOk()->assertJsonPath('data.status', $status->value);
        }
    }

    public function test_member_can_read_tasks_but_cannot_create_or_update_them(): void
    {
        [$workspace, $project, $member] = $this->projectWithUser(WorkspaceRole::Member);
        $task = Task::factory()->for($project)->create();

        $this->actingAs($member)->getJson($this->tasksUrl($workspace, $project), $this->spaHeaders())
            ->assertOk()->assertJsonPath('data.0.id', $task->id);
        $this->actingAs($member)->getJson($this->taskUrl($workspace, $project, $task), $this->spaHeaders())
            ->assertOk()->assertJsonPath('data.id', $task->id);
        $this->actingAs($member)->postJson($this->tasksUrl($workspace, $project), $this->taskPayload(), $this->spaHeaders())
            ->assertForbidden();
        $this->actingAs($member)->patchJson($this->taskUrl($workspace, $project, $task), ['status' => 'done'], $this->spaHeaders())
            ->assertForbidden();
    }

    public function test_outsider_and_mismatched_nested_task_routes_return_not_found(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);
        $otherProject = Project::factory()->for($workspace)->create();
        $otherWorkspace = Workspace::factory()->create();
        $task = Task::factory()->for($project)->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->getJson($this->tasksUrl($workspace, $project), $this->spaHeaders())->assertNotFound();
        $this->actingAs($owner)->getJson($this->taskUrl($workspace, $otherProject, $task), $this->spaHeaders())->assertNotFound();
        $this->actingAs($owner)->getJson($this->taskUrl($otherWorkspace, $project, $task), $this->spaHeaders())->assertNotFound();
    }

    public function test_task_routes_require_authentication_and_verified_email(): void
    {
        [$workspace, $project] = $this->projectWithUser(WorkspaceRole::Owner);
        $unverified = User::factory()->unverified()->create();
        WorkspaceMembership::factory()->for($workspace)->for($unverified)->create();

        $this->getJson($this->tasksUrl($workspace, $project), $this->spaHeaders())->assertUnauthorized();
        $this->actingAs($unverified)->getJson($this->tasksUrl($workspace, $project), $this->spaHeaders())->assertForbidden();
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

    /** @param array<string, mixed> $overrides */
    private function taskPayload(array $overrides = []): array
    {
        return [
            'title' => 'Ship project board',
            'description' => 'Build the authoritative Kanban experience.',
            'status' => TaskStatus::Todo->value,
            'priority' => TaskPriority::High->value,
            ...$overrides,
        ];
    }

    private function tasksUrl(Workspace $workspace, Project $project): string
    {
        return "/api/v1/workspaces/{$workspace->slug}/projects/{$project->slug}/tasks";
    }

    private function taskUrl(Workspace $workspace, Project $project, Task $task): string
    {
        return $this->tasksUrl($workspace, $project)."/{$task->id}";
    }
}
