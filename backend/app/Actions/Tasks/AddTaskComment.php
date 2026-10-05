<?php

namespace App\Actions\Tasks;

use App\Actions\Notifications\CreateUserNotification;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AddTaskComment
{
    public function __construct(
        private readonly RecordTaskActivity $recordActivity,
        private readonly CreateUserNotification $createUserNotification,
    ) {}

    public function handle(Task $task, User $author, string $body): TaskComment
    {
        return DB::transaction(function () use ($task, $author, $body): TaskComment {
            $task = $task->newQuery()->whereKey($task->getKey())->lockForUpdate()->firstOrFail();
            $comment = $task->comments()->create([
                'author_id' => $author->id,
                'body' => trim($body),
            ]);

            $this->recordActivity->record($task, $author, 'comment.added', [
                'comment_id' => $comment->id,
            ]);

            if ($task->assignee_id !== null) {
                $recipient = User::query()->findOrFail($task->assignee_id);
                $this->createUserNotification->taskCommentAdded($task, $comment, $author, $recipient);
            }

            return $comment;
        });
    }
}
