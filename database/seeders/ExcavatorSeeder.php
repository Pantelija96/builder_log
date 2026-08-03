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
        $hours = [
            850,
            1450,
            2330,
        ];

        $machines = Machine::query()
            ->where('type', MachineType::EXCAVATOR)
            ->orderBy('id')
            ->get();

        foreach ($machines as $index => $machine) {

            Excavator::create([

                'machine_id' => $machine->id,

                'initial_work_hours' => $hours[$index],

            ]);
        }
    }
}
