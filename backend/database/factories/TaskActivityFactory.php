<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TaskActivity> */
class TaskActivityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'actor_id' => User::factory(),
            'action' => 'task.created',
            'metadata' => [],
        ];
    }
}
