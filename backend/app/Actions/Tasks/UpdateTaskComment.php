<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateTaskComment
{
    public function __construct(private readonly RecordTaskActivity $recordActivity) {}

    public function handle(Task $task, TaskComment $comment, User $actor, string $body): TaskComment
    {
        return DB::transaction(function () use ($task, $comment, $actor, $body): TaskComment {
            $task = $task->newQuery()->whereKey($task->getKey())->lockForUpdate()->firstOrFail();
            $comment = $comment->newQuery()
                ->whereBelongsTo($task)
                ->whereKey($comment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $body = trim($body);

            if ($comment->body === $body) {
                return $comment;
            }

            $comment->update(['body' => $body]);
            $this->recordActivity->record($task, $actor, 'comment.edited', [
                'comment_id' => $comment->id,
            ]);

            return $comment->refresh();
        });
    }
}
