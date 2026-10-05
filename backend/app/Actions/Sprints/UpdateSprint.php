<?php

namespace App\Actions\Sprints;

use App\Enums\SprintStatus;
use App\Models\Project;
use App\Models\Sprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateSprint
{
    /** @param array{name: string, goal?: string|null, start_date: string, end_date: string, status: string} $data */
    public function handle(Project $project, Sprint $sprint, array $data): Sprint
    {
        return DB::transaction(function () use ($project, $sprint, $data): Sprint {
            $project->newQuery()->whereKey($project->getKey())->lockForUpdate()->firstOrFail();
            $sprint = $sprint->newQuery()->whereKey($sprint->getKey())->lockForUpdate()->firstOrFail();

            $currentStatus = $sprint->status;
            $nextStatus = SprintStatus::from($data['status']);
            $allowedTransitions = match ($currentStatus) {
                SprintStatus::Planned => [SprintStatus::Planned, SprintStatus::Active],
                SprintStatus::Active => [SprintStatus::Planned, SprintStatus::Active, SprintStatus::Completed],
                SprintStatus::Completed => [SprintStatus::Completed],
            };

            if (! in_array($nextStatus, $allowedTransitions, true)) {
                throw ValidationException::withMessages([
                    'status' => "A {$currentStatus->value} sprint cannot move to {$nextStatus->value}.",
                ]);
            }

            if ($nextStatus === SprintStatus::Active && $currentStatus !== SprintStatus::Active) {
                $hasActiveSprint = $project->sprints()
                    ->where('id', '!=', $sprint->getKey())
                    ->where('status', SprintStatus::Active)
                    ->exists();

                if ($hasActiveSprint) {
                    throw ValidationException::withMessages([
                        'status' => 'This project already has an active sprint.',
                    ]);
                }
            }

            $sprint->fill($data);

            if ($currentStatus === SprintStatus::Completed && $sprint->isDirty()) {
                throw ValidationException::withMessages([
                    'status' => 'A completed sprint cannot be changed.',
                ]);
            }

            $sprint->save();

            return $sprint->refresh();
        });
    }
}
