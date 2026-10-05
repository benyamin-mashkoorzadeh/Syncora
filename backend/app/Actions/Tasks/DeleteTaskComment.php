<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteTaskComment
{
    public function __construct(private readonly RecordTaskActivity $recordActivity) {}

    public function handle(Task $task, TaskComment $comment, User $actor): void
    {
        DB::transaction(function () use ($task, $comment, $actor): void {
            $task = $task->newQuery()->whereKey($task->getKey())->lockForUpdate()->firstOrFail();
            $comment = $comment->newQuery()
                ->whereBelongsTo($task)
                ->whereKey($comment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $comment->delete();
            $this->recordActivity->record($task, $actor, 'comment.deleted', [
                'comment_id' => $comment->id,
            ]);
        });
    }
}
