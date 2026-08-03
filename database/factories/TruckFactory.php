<?php

namespace Database\Factories;

use App\Enums\MachineType;
use App\Models\Machine;
use App\Models\Truck;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Truck>
 */
class TruckFactory extends Factory
{
    public function definition(): array
    {
        return [
            'machine_id' => Machine::factory()->state([
                'type' => MachineType::TRUCK,
            ]),
            'license_plate' => strtoupper(
                fake()->unique()->bothify('BG-###-??')
            ),
            'initial_mileage' => fake()->randomFloat(
                2,
                0,
                500000
            ),
        ];
    }
}
