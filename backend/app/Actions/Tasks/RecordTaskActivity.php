<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use Illuminate\Support\Str;

class RecordTaskActivity
{
    /** @param array<string, mixed> $metadata */
    public function record(Task $task, User $actor, string $action, array $metadata = []): TaskActivity
    {
        return $task->activities()->create([
            'actor_id' => $actor->id,
            'action' => $action,
            'metadata' => $metadata,
        ]);
    }

    /** @return array<string, mixed> */
    public function snapshot(Task $task): array
    {
        $assignee = $task->assignee()->first(['users.id', 'users.name']);
        $sprint = $task->sprint()->first(['sprints.id', 'sprints.name']);

        return [
            'title' => ['value' => $task->title],
            'description' => ['value' => $task->description],
            'status' => ['value' => $task->status->value, 'label' => match ($task->status) {
                TaskStatus::Todo => 'To Do',
                TaskStatus::InProgress => 'In Progress',
                TaskStatus::Done => 'Done',
            }],
            'priority' => ['value' => $task->priority->value, 'label' => Str::headline($task->priority->value)],
            'assignee' => ['id' => $assignee?->id, 'label' => $assignee?->name ?? 'Unassigned'],
            'sprint' => ['id' => $sprint?->id, 'label' => $sprint?->name ?? 'No sprint'],
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    /** @return array<string, TaskActivity> */
    public function recordChanges(Task $task, User $actor, array $before, array $after): array
    {
        $activities = [];

        foreach (['title', 'description', 'status', 'priority', 'assignee', 'sprint'] as $field) {
            if ($before[$field] === $after[$field]) {
                continue;
            }

            $activities[$field] = $this->record($task, $actor, "task.{$field}_changed", [
                'field' => $field,
                'from' => $before[$field],
                'to' => $after[$field],
            ]);
        }

        return $activities;
    }
}
