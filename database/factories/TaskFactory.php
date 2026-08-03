<?php

namespace Database\Factories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'priority' => null,
            'due_date' => fake()
                ->optional()
                ->dateTimeBetween('now', '+30 days'),
            'read_at' => null,
            'completed_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn () => [
            'read_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'read_at' => now()->subHour(),
            'completed_at' => now(),
        ]);
    }
}
