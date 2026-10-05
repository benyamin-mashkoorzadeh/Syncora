<?php

namespace App\Actions\Tasks;

use App\Actions\Notifications\CreateUserNotification;
use App\Enums\TaskStatus;
use App\Events\ProjectBoardChanged;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTask
{
    public function __construct(
        private readonly RecordTaskActivity $recordActivity,
        private readonly CreateUserNotification $createUserNotification,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(Project $project, User $actor, array $data): Task
    {
        return DB::transaction(function () use ($project, $actor, $data): Task {
            $project->newQuery()->whereKey($project->getKey())->lockForUpdate()->firstOrFail();
            $status = TaskStatus::tryFrom($data['status'] ?? '') ?? TaskStatus::Todo;
            $maximumPosition = $project->tasks()->where('status', $status)->max('position');
            $position = $maximumPosition === null ? 0 : ((int) $maximumPosition) + 1;

            $task = $project->tasks()->create([
                ...$data,
                'status' => $status,
                'position' => $position,
            ]);

            $activity = $this->recordActivity->record($task, $actor, 'task.created', [
                'title' => $task->title,
            ]);

            if ($task->assignee_id !== null) {
                $recipient = User::query()->findOrFail($task->assignee_id);
                $this->createUserNotification->taskAssigned($task, $actor, $recipient, "activity:{$activity->id}");
            }

            event((new ProjectBoardChanged($project->id, $task->id, 'created'))
                ->dontBroadcastToCurrentUser());

            return $task;
        });
    }
}
