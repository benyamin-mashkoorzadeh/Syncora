<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;

class ProjectPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $workspace->memberships()->whereBelongsTo($user)->exists();
    }

    public function view(User $user, Project $project): bool
    {
        return $project->workspace->memberships()->whereBelongsTo($user)->exists();
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $workspace->isOwner($user);
    }

    public function update(User $user, Project $project): bool
    {
        return $project->workspace->isOwner($user);
    }

    public function delete(User $user, Project $project): bool
    {
        return false;
    }
}
