<?php

namespace App\Actions\Tasks;

use App\Actions\Notifications\CreateUserNotification;
use App\Enums\TaskStatus;
use App\Events\ProjectBoardChanged;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateTask
{
    public function __construct(
        private readonly RecordTaskActivity $recordActivity,
        private readonly CreateUserNotification $createUserNotification,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(Project $project, Task $task, User $actor, array $data): Task
    {
        return DB::transaction(function () use ($project, $task, $actor, $data): Task {
            $project->newQuery()->whereKey($project->getKey())->lockForUpdate()->firstOrFail();
            $task = $task->newQuery()->whereKey($task->getKey())->lockForUpdate()->firstOrFail();
            $before = $this->recordActivity->snapshot($task);
            $beforeBoard = $this->boardSnapshot($task);

            $currentStatus = $task->status;
            $nextStatus = isset($data['status']) ? TaskStatus::from($data['status']) : $currentStatus;
            $moves = array_key_exists('position', $data)
                || (array_key_exists('status', $data) && $nextStatus !== $currentStatus);

            if (! $moves) {
                $task->update($data);
            } else {
                $sourceTasks = $project->tasks()
                    ->where('status', $currentStatus)
                    ->whereKeyNot($task->getKey())
                    ->orderBy('position')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $destinationTasks = $currentStatus === $nextStatus
                    ? $sourceTasks
                    : $project->tasks()
                        ->where('status', $nextStatus)
                        ->whereKeyNot($task->getKey())
                        ->orderBy('position')
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                $targetPosition = min((int) ($data['position'] ?? $destinationTasks->count()), $destinationTasks->count());
                unset($data['position']);
                $task->fill([...$data, 'status' => $nextStatus, 'position' => $targetPosition])->save();

                if ($currentStatus !== $nextStatus) {
                    foreach ($sourceTasks->values() as $position => $sourceTask) {
                        $sourceTask->update(['position' => $position]);
                    }
                }

                $destinationTasks->splice($targetPosition, 0, [$task]);
                foreach ($destinationTasks->values() as $position => $destinationTask) {
                    $destinationTask->update(['position' => $position]);
                }
            }

            $task = $task->refresh();
            $after = $this->recordActivity->snapshot($task);
            $activities = $this->recordActivity->recordChanges($task, $actor, $before, $after);

            if (isset($activities['assignee']) && $after['assignee']['id'] !== null) {
                $recipient = User::query()->findOrFail($after['assignee']['id']);
                $this->createUserNotification->taskAssigned(
                    $task,
                    $actor,
                    $recipient,
                    "activity:{$activities['assignee']->id}",
                );
            }

            if ($beforeBoard !== $this->boardSnapshot($task)) {
                event((new ProjectBoardChanged($project->id, $task->id, 'updated'))
                    ->dontBroadcastToCurrentUser());
            }

            return $task;
        });
    }

    /** @return array<string, mixed> */
    private function boardSnapshot(Task $task): array
    {
        return $task->only([
            'title',
            'description',
            'status',
            'priority',
            'position',
            'sprint_id',
            'assignee_id',
        ]);
    }
}
