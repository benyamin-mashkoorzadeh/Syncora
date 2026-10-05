<?php

namespace Tests\Feature\Project;

use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_can_create_project_and_server_assigns_workspace_and_stable_slug(): void
    {
        [$workspace, $owner] = $this->workspaceWithUser(WorkspaceRole::Owner);
        $otherWorkspace = Workspace::factory()->create();

        $response = $this->actingAs($owner)->postJson($this->projectsUrl($workspace), [
            'name' => '  Mobile Experience  ',
            'description' => 'A focused product initiative.',
            'workspace_id' => $otherWorkspace->id,
            'slug' => 'tampered',
        ], $this->spaHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Mobile Experience')
            ->assertJsonPath('data.slug', 'mobile-experience')
            ->assertJsonMissingPath('data.workspace_id');
        $this->assertDatabaseHas('projects', [
            'workspace_id' => $workspace->id,
            'slug' => 'mobile-experience',
        ]);
    }

    public function test_project_creation_validates_name_and_description(): void
    {
        [$workspace, $owner] = $this->workspaceWithUser(WorkspaceRole::Owner);

        $this->actingAs($owner)->postJson($this->projectsUrl($workspace), [
            'name' => '',
            'description' => str_repeat('x', 5001),
        ], $this->spaHeaders())->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'description']);
    }

    public function test_member_lists_and_views_only_projects_in_their_workspace(): void
    {
        [$workspace, $member] = $this->workspaceWithUser(WorkspaceRole::Member);
        $project = Project::factory()->for($workspace)->create(['name' => 'Visible']);
        $hidden = Project::factory()->create(['name' => 'Hidden']);

        $this->actingAs($member)->getJson($this->projectsUrl($workspace), $this->spaHeaders())
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $project->id)
            ->assertJsonMissing(['id' => $hidden->id]);
        $this->actingAs($member)->getJson($this->projectUrl($workspace, $project), $this->spaHeaders())
            ->assertOk()->assertJsonPath('data.id', $project->id);
    }

    public function test_owner_can_update_project_without_changing_slug(): void
    {
        [$workspace, $owner] = $this->workspaceWithUser(WorkspaceRole::Owner);
        $project = Project::factory()->for($workspace)->create(['slug' => 'stable-project']);

        $this->actingAs($owner)->patchJson($this->projectUrl($workspace, $project), [
            'name' => 'Renamed Project',
            'description' => 'Updated description.',
            'slug' => 'changed-project',
        ], $this->spaHeaders())->assertOk()
            ->assertJsonPath('data.name', 'Renamed Project')
            ->assertJsonPath('data.slug', 'stable-project');
    }

    public function test_member_cannot_create_or_update_projects(): void
    {
        [$workspace, $member] = $this->workspaceWithUser(WorkspaceRole::Member);
        $project = Project::factory()->for($workspace)->create();

        $this->actingAs($member)->postJson($this->projectsUrl($workspace), [
            'name' => 'Denied',
        ], $this->spaHeaders())->assertForbidden();
        $this->actingAs($member)->patchJson($this->projectUrl($workspace, $project), [
            'name' => 'Denied',
        ], $this->spaHeaders())->assertForbidden();
    }

    public function test_outsider_and_cross_workspace_project_access_return_not_found(): void
    {
        [$workspace, $owner] = $this->workspaceWithUser(WorkspaceRole::Owner);
        [$otherWorkspace] = $this->workspaceWithUser(WorkspaceRole::Owner);
        $project = Project::factory()->for($workspace)->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->getJson($this->projectsUrl($workspace), $this->spaHeaders())
            ->assertNotFound();
        $this->actingAs($owner)->getJson($this->projectUrl($otherWorkspace, $project), $this->spaHeaders())
            ->assertNotFound();
    }

    public function test_project_routes_require_authentication_and_verified_email(): void
    {
        [$workspace] = $this->workspaceWithUser(WorkspaceRole::Owner);
        $unverified = User::factory()->unverified()->create();
        WorkspaceMembership::factory()->for($workspace)->for($unverified)->create();

        $this->getJson($this->projectsUrl($workspace), $this->spaHeaders())->assertUnauthorized();
        $this->actingAs($unverified)->getJson($this->projectsUrl($workspace), $this->spaHeaders())
            ->assertForbidden();
    }

    /** @return array{Workspace, User} */
    private function workspaceWithUser(WorkspaceRole $role): array
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($user)->create(['role' => $role]);

        return [$workspace, $user];
    }

    private function projectsUrl(Workspace $workspace): string
    {
        return "/api/v1/workspaces/{$workspace->slug}/projects";
    }

    private function projectUrl(Workspace $workspace, Project $project): string
    {
        return $this->projectsUrl($workspace)."/{$project->slug}";
    }
}
