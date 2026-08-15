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
        $plates = [
            'BG-101-AA',
            'BG-102-AA',
            'BG-103-AA',
            'BG-104-AA',
            'BG-105-AA',
            'BG-106-AA',
            'BG-107-AA',
            'BG-108-AA',
        ];

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
                'license_plate' => $plates[$index],
                'initial_mileage' => $mileages[$index],
            ]);
        }
    }
}
