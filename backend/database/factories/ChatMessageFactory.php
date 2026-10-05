<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'sender_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
