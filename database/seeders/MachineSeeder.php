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
                'license_plate' => null,
            ],
            [
                'name' => 'Hitachi ZX210',
                'type' => MachineType::EXCAVATOR,
                'license_plate' => null,
            ],
            [
                'name' => 'Komatsu PC210',
                'type' => MachineType::EXCAVATOR,
                'license_plate' => null,
            ],
            [
                'name' => 'Volvo EC220E',
                'type' => MachineType::EXCAVATOR,
                'license_plate' => null,
            ],
            [
                'name' => 'JCB JS220',
                'type' => MachineType::EXCAVATOR,
                'license_plate' => null,
            ],
            [
                'name' => 'Hyundai HX220L',
                'type' => MachineType::EXCAVATOR,
                'license_plate' => null,
            ],
            [
                'name' => 'Doosan DX225LC',
                'type' => MachineType::EXCAVATOR,
                'license_plate' => null,
            ],
            [
                'name' => 'Liebherr R922',
                'type' => MachineType::EXCAVATOR,
                'license_plate' => null,
            ],
            [
                'name' => 'Case CX210D',
                'type' => MachineType::EXCAVATOR,
                'license_plate' => null,
            ],
            [
                'name' => 'Kobelco SK210',
                'type' => MachineType::EXCAVATOR,
                'license_plate' => null,
            ],
            [
                'name' => 'Caterpillar 323',
                'type' => MachineType::EXCAVATOR,
                'license_plate' => null,
            ],
            [
                'name' => 'Hitachi ZX250',
                'type' => MachineType::EXCAVATOR,
                'license_plate' => null,
            ],
            [
                'name' => 'Komatsu PC240',
                'type' => MachineType::EXCAVATOR,
                'license_plate' => null,
            ],
            [
                'name' => 'Volvo EC250E',
                'type' => MachineType::EXCAVATOR,
                'license_plate' => null,
            ],
            [
                'name' => 'JCB JS300',
                'type' => MachineType::EXCAVATOR,
                'license_plate' => null,
            ],

            /*
            |--------------------------------------------------------------------------
            | Trucks
            |--------------------------------------------------------------------------
            */

            [
                'name' => 'Mercedes Actros',
                'type' => MachineType::TRUCK,
                'license_plate' => 'BG-101-AA',
            ],
            [
                'name' => 'MAN TGS',
                'type' => MachineType::TRUCK,
                'license_plate' => 'BG-102-AA',
            ],
            [
                'name' => 'Volvo FMX',
                'type' => MachineType::TRUCK,
                'license_plate' => 'BG-103-AA',
            ],
            [
                'name' => 'Scania R450',
                'type' => MachineType::TRUCK,
                'license_plate' => 'BG-104-AA',
            ],
            [
                'name' => 'DAF XF',
                'type' => MachineType::TRUCK,
                'license_plate' => 'BG-105-AA',
            ],
            [
                'name' => 'Iveco Trakker',
                'type' => MachineType::TRUCK,
                'license_plate' => 'BG-106-AA',
            ],
            [
                'name' => 'Renault K',
                'type' => MachineType::TRUCK,
                'license_plate' => 'BG-107-AA',
            ],
            [
                'name' => 'Ford F-Max',
                'type' => MachineType::TRUCK,
                'license_plate' => 'BG-108-AA',
            ],
        ];

        foreach ($machines as $machine) {
            $machineModel = new Machine([
                'company_id' => $company->id,
                'name' => $machine['name'],
                'type' => $machine['type'],
                'status' => MachineStatus::ACTIVE,
                'image_path' => null,
                'license_plate' => $machine['license_plate'],
            ]);

            $machineModel->owner()->associate($company);

            $machineModel->save();
        }
    }
}
