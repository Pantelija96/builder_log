<?php

namespace Database\Factories;

use App\Enums\MachineType;
use App\Models\Excavator;
use App\Models\Machine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Excavator>
 */
class ExcavatorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'machine_id' => Machine::factory()->state([
                'type' => MachineType::EXCAVATOR,
            ]),
            'initial_work_hours' => fake()->randomFloat(2, 0, 15000),
        ];
    }
}
