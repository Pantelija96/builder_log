<?php

namespace Database\Seeders;

use App\Enums\MachineStatus;
use App\Enums\MachineType;
use App\Models\Company;
use App\Models\Machine;
use Illuminate\Database\Seeder;

class MachineSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->firstOrFail();

        $machines = [

            /*
            |--------------------------------------------------------------------------
            | Excavators
            |--------------------------------------------------------------------------
            */

            [
                'name' => 'CAT 320',
                'type' => MachineType::EXCAVATOR,
                'status' => MachineStatus::ACTIVE,
            ],

            [
                'name' => 'Hitachi ZX210',
                'type' => MachineType::EXCAVATOR,
                'status' => MachineStatus::ACTIVE,
            ],

            [
                'name' => 'Komatsu PC210',
                'type' => MachineType::EXCAVATOR,
                'status' => MachineStatus::SERVICE,
            ],

            /*
            |--------------------------------------------------------------------------
            | Trucks
            |--------------------------------------------------------------------------
            */

            [
                'name' => 'Mercedes Actros',
                'type' => MachineType::TRUCK,
                'status' => MachineStatus::ACTIVE,
            ],

            [
                'name' => 'MAN TGS',
                'type' => MachineType::TRUCK,
                'status' => MachineStatus::ACTIVE,
            ],

            [
                'name' => 'Volvo FMX',
                'type' => MachineType::TRUCK,
                'status' => MachineStatus::BROKEN,
            ],

        ];

        foreach ($machines as $machine) {

            $machineModel = new Machine([
                'company_id' => $company->id,
                'name' => $machine['name'],
                'type' => $machine['type'],
                'status' => $machine['status'],
                'image_path' => null,
            ]);

            $machineModel->owner()->associate($company);

            $machineModel->save();
        }
    }
}
