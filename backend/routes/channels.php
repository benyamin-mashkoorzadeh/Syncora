<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('project.{projectId}.collaboration', function (User $user, int $projectId): array|false {
    if (! $user->hasVerifiedEmail()) {
        return false;
    }

    $project = Project::query()->select(['id', 'workspace_id'])->find($projectId);

    if (! $project || ! $user->workspaceMemberships()->where('workspace_id', $project->workspace_id)->exists()) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'avatar_url' => $user->avatarUrl(),
    ];
});

Broadcast::channel('user.{userId}.notifications', function (User $user, int $userId): bool {
    return $user->hasVerifiedEmail() && $user->id === $userId;
});
