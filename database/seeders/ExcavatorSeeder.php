<?php

namespace Database\Seeders;

use App\Enums\MachineType;
use App\Models\Excavator;
use App\Models\Machine;
use Illuminate\Database\Seeder;

class ExcavatorSeeder extends Seeder
{
    public function run(): void
    {
        $machines = Machine::query()
            ->where('type', MachineType::EXCAVATOR)
            ->orderBy('id')
            ->get();

        foreach ($machines as $machine) {

            $hours = fake()->numberBetween(500, 5000);

            Excavator::create([
                'machine_id' => $machine->id,
                'initial_work_hours' => $hours,
                'total_work_hours' => $hours,
            ]);
        }
    }
}
