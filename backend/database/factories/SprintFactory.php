<?php

namespace Database\Factories;

use App\Enums\SprintStatus;
use App\Models\Project;
use App\Models\Sprint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sprint>
 */
class SprintFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 month', '+1 month');

        return [
            'project_id' => Project::factory(),
            'name' => 'Sprint '.fake()->unique()->numberBetween(1, 999),
            'goal' => fake()->optional()->sentence(12),
            'start_date' => $startDate,
            'end_date' => (clone $startDate)->modify('+13 days'),
            'status' => SprintStatus::Planned,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => SprintStatus::Active]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => SprintStatus::Completed]);
    }
}
