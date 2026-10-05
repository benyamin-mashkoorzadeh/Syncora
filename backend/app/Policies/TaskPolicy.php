<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $project->workspace->memberships()->whereBelongsTo($user)->exists();
    }

    public function view(User $user, Task $task): bool
    {
        return $task->project->workspace->memberships()->whereBelongsTo($user)->exists();
    }

    public function create(User $user, Project $project): bool
    {
        return $project->workspace->isOwner($user);
    }

    public function update(User $user, Task $task): bool
    {
        return $task->project->workspace->isOwner($user);
    }

    public function delete(User $user, Task $task): bool
    {
        return false;
    }
}
