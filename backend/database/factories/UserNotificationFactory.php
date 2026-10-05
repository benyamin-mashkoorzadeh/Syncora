<?php

namespace Database\Factories;

use App\Enums\UserNotificationType;
use App\Models\Task;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UserNotification>
 */
class UserNotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'recipient_id' => User::factory(),
            'actor_id' => User::factory(),
            'task_id' => Task::factory(),
            'project_id' => fn (array $attributes): int => Task::query()->findOrFail($attributes['task_id'])->project_id,
            'type' => UserNotificationType::TaskAssigned,
            'deduplication_key' => 'factory:'.Str::uuid(),
            'read_at' => null,
        ];
    }
}
