<?php

namespace App\Actions\Notifications;

use App\Enums\UserNotificationType;
use App\Events\UserNotificationCreated;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\UserNotification;

class CreateUserNotification
{
    public function taskAssigned(Task $task, User $actor, User $recipient, string $source): ?UserNotification
    {
        return $this->create(
            $task,
            $actor,
            $recipient,
            UserNotificationType::TaskAssigned,
            "task-assigned:{$source}:{$recipient->id}",
        );
    }

    public function taskCommentAdded(Task $task, TaskComment $comment, User $actor, User $recipient): ?UserNotification
    {
        return $this->create(
            $task,
            $actor,
            $recipient,
            UserNotificationType::TaskCommentAdded,
            "task-comment:{$comment->id}:{$recipient->id}",
        );
    }

    private function create(
        Task $task,
        User $actor,
        User $recipient,
        UserNotificationType $type,
        string $deduplicationKey,
    ): ?UserNotification {
        $workspaceId = $task->project()->value('workspace_id');

        if ($actor->is($recipient) || ! $recipient->workspaceMemberships()->where('workspace_id', $workspaceId)->exists()) {
            return null;
        }

        $notification = UserNotification::query()->firstOrCreate(
            ['deduplication_key' => $deduplicationKey],
            [
                'recipient_id' => $recipient->id,
                'actor_id' => $actor->id,
                'project_id' => $task->project_id,
                'task_id' => $task->id,
                'type' => $type,
            ],
        );

        if ($notification->wasRecentlyCreated) {
            event(new UserNotificationCreated($recipient->id, $notification->id));
        }

        return $notification;
    }
}
