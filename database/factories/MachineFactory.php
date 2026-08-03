<?php

namespace Database\Factories;

use App\Enums\MachineStatus;
use App\Enums\MachineType;
use App\Models\Company;
use App\Models\Machine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Machine>
 */
class MachineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Machine ' . fake()->unique()->numberBetween(1, 10000),
            'type' => MachineType::EXCAVATOR,
            'status' => MachineStatus::ACTIVE,
            'image_path' => null,
        ];
    }

    public function excavator(): static
    {
        return $this->state(fn () => [
            'type' => MachineType::EXCAVATOR,
        ]);
    }

    public function truck(): static
    {
        return $this->state(fn () => [
            'type' => MachineType::TRUCK,
        ]);
    }
}
