<?php

namespace Tests\Feature\Sprint;

use App\Enums\SprintStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SprintTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_can_create_planned_sprint_and_server_assigns_project_and_status(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);
        $otherProject = Project::factory()->for($workspace)->create();

        $this->actingAs($owner)->postJson($this->sprintsUrl($workspace, $project), [
            'name' => 'Sprint One',
            'goal' => 'Establish the first shippable increment.',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-14',
            'status' => 'active',
            'project_id' => $otherProject->id,
        ], $this->spaHeaders())->assertCreated()
            ->assertJsonPath('data.status', 'planned')
            ->assertJsonPath('data.name', 'Sprint One')
            ->assertJsonMissingPath('data.project_id');

        $this->assertDatabaseHas('sprints', [
            'project_id' => $project->id,
            'name' => 'Sprint One',
            'status' => SprintStatus::Planned->value,
        ]);
    }

    public function test_sprint_creation_validates_required_fields_and_date_order(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);

        $this->actingAs($owner)->postJson($this->sprintsUrl($workspace, $project), [
            'name' => '',
            'start_date' => '2026-10-20',
            'end_date' => '2026-10-10',
        ], $this->spaHeaders())->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'end_date']);
    }

    public function test_member_can_list_and_view_sprints_but_cannot_mutate_them(): void
    {
        [$workspace, $project, $member] = $this->projectWithUser(WorkspaceRole::Member);
        $sprint = Sprint::factory()->for($project)->create();

        $this->actingAs($member)->getJson($this->sprintsUrl($workspace, $project), $this->spaHeaders())
            ->assertOk()->assertJsonPath('data.0.id', $sprint->id);
        $this->actingAs($member)->getJson($this->sprintUrl($workspace, $project, $sprint), $this->spaHeaders())
            ->assertOk()->assertJsonPath('data.id', $sprint->id);
        $this->actingAs($member)->postJson($this->sprintsUrl($workspace, $project), $this->sprintPayload(), $this->spaHeaders())
            ->assertForbidden();
        $this->actingAs($member)->patchJson($this->sprintUrl($workspace, $project, $sprint), [
            ...$this->sprintPayload(),
            'status' => 'active',
        ], $this->spaHeaders())->assertForbidden();
    }

    public function test_outsider_and_mismatched_nested_sprint_routes_return_not_found(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);
        $otherProject = Project::factory()->for($workspace)->create();
        $otherWorkspace = Workspace::factory()->create();
        $sprint = Sprint::factory()->for($project)->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->getJson($this->sprintsUrl($workspace, $project), $this->spaHeaders())
            ->assertNotFound();
        $this->actingAs($owner)->getJson($this->sprintUrl($workspace, $otherProject, $sprint), $this->spaHeaders())
            ->assertNotFound();
        $this->actingAs($owner)->getJson($this->sprintUrl($otherWorkspace, $project, $sprint), $this->spaHeaders())
            ->assertNotFound();
    }

    public function test_owner_can_progress_sprint_forward_and_completed_sprint_is_immutable(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);
        $sprint = Sprint::factory()->for($project)->create();

        $this->actingAs($owner)->patchJson($this->sprintUrl($workspace, $project, $sprint), [
            ...$this->sprintPayload(),
            'status' => 'active',
        ], $this->spaHeaders())->assertOk()->assertJsonPath('data.status', 'active');
        $this->actingAs($owner)->patchJson($this->sprintUrl($workspace, $project, $sprint), [
            ...$this->sprintPayload(),
            'status' => 'completed',
        ], $this->spaHeaders())->assertOk()->assertJsonPath('data.status', 'completed');
        $this->actingAs($owner)->patchJson($this->sprintUrl($workspace, $project, $sprint), [
            ...$this->sprintPayload(['name' => 'Changed after completion']),
            'status' => 'completed',
        ], $this->spaHeaders())->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_active_sprint_can_return_to_planned_and_releases_the_active_slot(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);
        $active = Sprint::factory()->for($project)->active()->create();
        $planned = Sprint::factory()->for($project)->create();

        $this->actingAs($owner)->patchJson($this->sprintUrl($workspace, $project, $active), [
            ...$this->sprintPayload(['name' => $active->name]),
            'status' => 'planned',
        ], $this->spaHeaders())->assertOk()->assertJsonPath('data.status', 'planned');
        $this->actingAs($owner)->patchJson($this->sprintUrl($workspace, $project, $planned), [
            ...$this->sprintPayload(['name' => $planned->name]),
            'status' => 'active',
        ], $this->spaHeaders())->assertOk()->assertJsonPath('data.status', 'active');
        $this->actingAs($owner)->patchJson($this->sprintUrl($workspace, $project, $active), [
            ...$this->sprintPayload(['name' => $active->name]),
            'status' => 'active',
        ], $this->spaHeaders())->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'This project already has an active sprint.');
    }

    public function test_completed_sprint_cannot_reopen_as_active_or_planned(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);
        $completed = Sprint::factory()->for($project)->completed()->create();

        foreach (['active', 'planned'] as $status) {
            $this->actingAs($owner)->patchJson($this->sprintUrl($workspace, $project, $completed), [
                ...$this->sprintPayload(['name' => $completed->name]),
                'status' => $status,
            ], $this->spaHeaders())->assertUnprocessable()->assertJsonValidationErrors('status');
        }
    }

    public function test_sprint_routes_require_authentication_and_verified_email(): void
    {
        [$workspace, $project] = $this->projectWithUser(WorkspaceRole::Owner);
        $unverified = User::factory()->unverified()->create();
        WorkspaceMembership::factory()->for($workspace)->for($unverified)->create();

        $this->getJson($this->sprintsUrl($workspace, $project), $this->spaHeaders())->assertUnauthorized();
        $this->actingAs($unverified)->getJson($this->sprintsUrl($workspace, $project), $this->spaHeaders())
            ->assertForbidden();
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

    /** @param array<string, string> $overrides */
    private function sprintPayload(array $overrides = []): array
    {
        return [
            'name' => 'Sprint One',
            'goal' => 'Deliver the planned outcome.',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-14',
            ...$overrides,
        ];
    }

    private function sprintsUrl(Workspace $workspace, Project $project): string
    {
        return "/api/v1/workspaces/{$workspace->slug}/projects/{$project->slug}/sprints";
    }

    private function sprintUrl(Workspace $workspace, Project $project, Sprint $sprint): string
    {
        return $this->sprintsUrl($workspace, $project)."/{$sprint->id}";
    }
}
