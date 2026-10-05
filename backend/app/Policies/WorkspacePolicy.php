<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;

class WorkspacePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->memberships()->whereBelongsTo($user)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Workspace $workspace): bool
    {
        return $workspace->isOwner($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function viewMembers(User $user, Workspace $workspace): bool
    {
        return $this->view($user, $workspace);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function addMember(User $user, Workspace $workspace): bool
    {
        return $workspace->isOwner($user);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function removeMember(
        User $user,
        Workspace $workspace,
        WorkspaceMembership $membership,
    ): bool {
        return $membership->workspace_id === $workspace->getKey()
            && $workspace->isOwner($user);
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return false;
    }
}
