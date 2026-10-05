<?php

namespace Tests\Feature\Workspace;

use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_verified_user_can_create_workspace_with_owner_membership_atomically(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/workspaces', [
            'name' => '  Product Lab  ',
            'owner_id' => 999,
            'role' => 'member',
        ], $this->spaHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Product Lab')
            ->assertJsonPath('data.slug', 'product-lab')
            ->assertJsonPath('data.membership.role', 'owner')
            ->assertJsonPath('data.member_count', 1)
            ->assertJsonMissingPath('data.owner_id');

        $workspace = Workspace::firstOrFail();
        $this->assertDatabaseHas('workspace_memberships', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Owner->value,
        ]);
    }

    public function test_workspace_names_are_validated_and_duplicate_names_receive_unique_slugs(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/workspaces', ['name' => ''], $this->spaHeaders())
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->actingAs($user)->postJson('/api/v1/workspaces', ['name' => 'Studio'], $this->spaHeaders())
            ->assertCreated()->assertJsonPath('data.slug', 'studio');
        $this->actingAs($user)->postJson('/api/v1/workspaces', ['name' => 'Studio'], $this->spaHeaders())
            ->assertCreated()->assertJsonPath('data.slug', 'studio-2');
    }

    public function test_workspace_slugs_do_not_conflict_with_frontend_application_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/workspaces', ['name' => 'Login'], $this->spaHeaders())
            ->assertCreated()
            ->assertJsonPath('data.slug', 'workspace-login');
    }

    public function test_workspace_creation_rolls_back_if_owner_membership_fails(): void
    {
        $user = User::factory()->create();
        WorkspaceMembership::creating(function (): never {
            throw new RuntimeException('Simulated membership failure.');
        });

        try {
            app(CreateWorkspace::class)->handle($user, 'Rollback workspace');
            $this->fail('The simulated membership failure was not raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated membership failure.', $exception->getMessage());
        } finally {
            WorkspaceMembership::flushEventListeners();
        }

        $this->assertDatabaseMissing('workspaces', ['slug' => 'rollback-workspace']);
        $this->assertDatabaseCount('workspace_memberships', 0);
    }

    public function test_list_only_returns_workspaces_for_current_user(): void
    {
        $user = User::factory()->create();
        $own = $this->workspaceFor($user, WorkspaceRole::Member, ['name' => 'Alpha']);
        $other = Workspace::factory()->create(['name' => 'Hidden']);

        $this->actingAs($user)->getJson('/api/v1/workspaces', $this->spaHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonMissing(['id' => $other->id]);
    }

    public function test_member_can_view_workspace_but_outsider_receives_not_found(): void
    {
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $workspace = $this->workspaceFor($member);

        $this->actingAs($member)->getJson("/api/v1/workspaces/{$workspace->slug}", $this->spaHeaders())
            ->assertOk()->assertJsonPath('data.membership.role', 'member');
        $this->actingAs($outsider)->getJson("/api/v1/workspaces/{$workspace->slug}", $this->spaHeaders())
            ->assertNotFound();
    }

    public function test_owner_can_rename_workspace_without_changing_stable_slug(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceFor($owner, WorkspaceRole::Owner, ['name' => 'Original', 'slug' => 'original']);

        $this->actingAs($owner)->patchJson("/api/v1/workspaces/{$workspace->slug}", [
            'name' => 'Renamed workspace',
            'slug' => 'tampered',
        ], $this->spaHeaders())->assertOk()
            ->assertJsonPath('data.name', 'Renamed workspace')
            ->assertJsonPath('data.slug', 'original');
    }

    public function test_regular_member_cannot_update_workspace(): void
    {
        $member = User::factory()->create();
        $workspace = $this->workspaceFor($member);

        $this->actingAs($member)->patchJson("/api/v1/workspaces/{$workspace->slug}", [
            'name' => 'No access',
        ], $this->spaHeaders())->assertForbidden();
    }

    public function test_workspace_endpoints_require_authentication_and_verified_email(): void
    {
        $unverified = User::factory()->unverified()->create();

        $this->getJson('/api/v1/workspaces', $this->spaHeaders())->assertUnauthorized();
        $this->actingAs($unverified)->getJson('/api/v1/workspaces', $this->spaHeaders())
            ->assertForbidden();
        $this->actingAs($unverified)->postJson('/api/v1/workspaces', ['name' => 'Nope'], $this->spaHeaders())
            ->assertForbidden();
    }

    private function workspaceFor(
        User $user,
        WorkspaceRole $role = WorkspaceRole::Member,
        array $attributes = [],
    ): Workspace {
        $workspace = Workspace::factory()->create($attributes);
        WorkspaceMembership::factory()->for($workspace)->for($user)->create(['role' => $role]);

        return $workspace;
    }
}
