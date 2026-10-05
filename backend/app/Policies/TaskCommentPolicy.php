<?php

namespace App\Policies;

use App\Models\TaskComment;
use App\Models\User;

class TaskCommentPolicy
{
    public function update(User $user, TaskComment $comment): bool
    {
        return $comment->author_id === $user->id
            && $comment->task->project->workspace->memberships()->whereBelongsTo($user)->exists();
    }

    public function delete(User $user, TaskComment $comment): bool
    {
        return $this->update($user, $comment);
    }
}
