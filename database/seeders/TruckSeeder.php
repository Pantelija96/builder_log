<?php

namespace Database\Seeders;

use App\Enums\MachineType;
use App\Models\Machine;
use App\Models\Truck;
use Illuminate\Database\Seeder;

class TruckSeeder extends Seeder
{
    public function run(): void
    {
        $mileages = [
            125000,
            184000,
            268000,
            143000,
            217000,
            305000,
            167000,
            241000,
        ];

        $machines = Machine::query()
            ->where('type', MachineType::TRUCK)
            ->orderBy('id')
            ->get();

        foreach ($machines as $index => $machine) {
            Truck::create([
                'machine_id' => $machine->id,
                'initial_mileage' => $mileages[$index],
            ]);
        }
    }
}
