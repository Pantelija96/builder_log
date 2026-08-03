<?php

namespace Database\Factories;

use App\Models\Machine;
use App\Models\MachineAssignment;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MachineAssignment>
 */
class MachineAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function ($assignment) {
            //
        });
    }
}
