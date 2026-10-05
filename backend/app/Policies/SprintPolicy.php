<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;

class SprintPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $project->workspace->memberships()->whereBelongsTo($user)->exists();
    }

    public function view(User $user, Sprint $sprint): bool
    {
        return $sprint->project->workspace->memberships()->whereBelongsTo($user)->exists();
    }

    public function create(User $user, Project $project): bool
    {
        return $project->workspace->isOwner($user);
    }

    public function update(User $user, Sprint $sprint): bool
    {
        return $sprint->project->workspace->isOwner($user);
    }

    public function delete(User $user, Sprint $sprint): bool
    {
        return false;
    }
}
