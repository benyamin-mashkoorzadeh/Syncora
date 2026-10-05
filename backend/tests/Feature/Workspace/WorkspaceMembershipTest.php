<?php

namespace Tests\Feature\Workspace;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class WorkspaceMembershipTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_workspace_member_can_list_members_with_safe_resource_shape(): void
    {
        [$workspace, $owner] = $this->ownedWorkspace();
        $member = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($member)->create();

        $this->actingAs($member)->getJson("/api/v1/workspaces/{$workspace->slug}/members", $this->spaHeaders())
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.user.id', $owner->id)
            ->assertJsonPath('data.0.role', 'owner')
            ->assertJsonMissingPath('data.0.user.password')
            ->assertJsonMissingPath('data.0.user.remember_token');
    }

    public function test_owner_can_add_existing_user_by_normalized_email_and_client_role_is_ignored(): void
    {
        [$workspace, $owner] = $this->ownedWorkspace();
        $user = User::factory()->create(['email' => 'person@example.test']);

        $this->actingAs($owner)->postJson("/api/v1/workspaces/{$workspace->slug}/members", [
            'email' => '  PERSON@EXAMPLE.TEST ',
            'role' => 'owner',
            'user_id' => $owner->id,
        ], $this->spaHeaders())->assertCreated()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.role', 'member');
    }

    public function test_unknown_and_duplicate_member_email_return_validation_errors(): void
    {
        [$workspace, $owner] = $this->ownedWorkspace();

        $this->actingAs($owner)->postJson("/api/v1/workspaces/{$workspace->slug}/members", [
            'email' => 'unknown@example.test',
        ], $this->spaHeaders())->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->actingAs($owner)->postJson("/api/v1/workspaces/{$workspace->slug}/members", [
            'email' => $owner->email,
        ], $this->spaHeaders())->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_regular_member_cannot_add_or_remove_members(): void
    {
        [$workspace, $owner] = $this->ownedWorkspace();
        $member = User::factory()->create();
        $target = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($member)->create();
        $targetMembership = WorkspaceMembership::factory()->for($workspace)->for($target)->create();

        $this->actingAs($member)->postJson("/api/v1/workspaces/{$workspace->slug}/members", [
            'email' => User::factory()->create()->email,
        ], $this->spaHeaders())->assertForbidden();
        $this->actingAs($member)->deleteJson(
            "/api/v1/workspaces/{$workspace->slug}/members/{$targetMembership->id}",
            [],
            $this->spaHeaders(),
        )->assertForbidden();
    }

    public function test_owner_can_remove_member_but_cannot_remove_owner(): void
    {
        [$workspace, $owner, $ownerMembership] = $this->ownedWorkspace();
        $member = User::factory()->create();
        $membership = WorkspaceMembership::factory()->for($workspace)->for($member)->create();

        $this->actingAs($owner)->deleteJson(
            "/api/v1/workspaces/{$workspace->slug}/members/{$membership->id}", [], $this->spaHeaders(),
        )->assertOk()->assertJsonPath('message', 'Member removed.');
        $this->assertDatabaseMissing('workspace_memberships', ['id' => $membership->id]);

        $this->actingAs($owner)->deleteJson(
            "/api/v1/workspaces/{$workspace->slug}/members/{$ownerMembership->id}", [], $this->spaHeaders(),
        )->assertUnprocessable()->assertJsonValidationErrors('member');
    }

    public function test_membership_ids_are_scoped_to_workspace_and_outsiders_see_not_found(): void
    {
        [$workspace, $owner] = $this->ownedWorkspace();
        [$otherWorkspace, , $otherMembership] = $this->ownedWorkspace();
        $outsider = User::factory()->create();
        $target = User::factory()->create();
        $targetMembership = WorkspaceMembership::factory()->for($workspace)->for($target)->create();

        $this->actingAs($owner)->deleteJson(
            "/api/v1/workspaces/{$workspace->slug}/members/{$otherMembership->id}", [], $this->spaHeaders(),
        )->assertNotFound();
        $this->actingAs($outsider)->getJson(
            "/api/v1/workspaces/{$workspace->slug}/members", $this->spaHeaders(),
        )->assertNotFound();
        $this->actingAs($outsider)->deleteJson(
            "/api/v1/workspaces/{$workspace->slug}/members/{$targetMembership->id}", [], $this->spaHeaders(),
        )->assertNotFound();

        $this->assertDatabaseHas('workspaces', ['id' => $otherWorkspace->id]);
    }

    public function test_database_prevents_duplicate_workspace_membership(): void
    {
        [$workspace, $owner] = $this->ownedWorkspace();

        $this->expectException(QueryException::class);
        WorkspaceMembership::factory()->for($workspace)->for($owner)->create();
    }

    /** @return array{Workspace, User, WorkspaceMembership} */
    private function ownedWorkspace(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $membership = WorkspaceMembership::factory()->for($workspace)->for($owner)->owner()->create();

        return [$workspace, $owner, $membership];
    }
}
