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
            ],
            [
                'name' => 'Hitachi ZX210',
                'type' => MachineType::EXCAVATOR,
            ],
            [
                'name' => 'Komatsu PC210',
                'type' => MachineType::EXCAVATOR,
            ],
            [
                'name' => 'Volvo EC220E',
                'type' => MachineType::EXCAVATOR,
            ],
            [
                'name' => 'JCB JS220',
                'type' => MachineType::EXCAVATOR,
            ],
            [
                'name' => 'Hyundai HX220L',
                'type' => MachineType::EXCAVATOR,
            ],
            [
                'name' => 'Doosan DX225LC',
                'type' => MachineType::EXCAVATOR,
            ],
            [
                'name' => 'Liebherr R922',
                'type' => MachineType::EXCAVATOR,
            ],
            [
                'name' => 'Case CX210D',
                'type' => MachineType::EXCAVATOR,
            ],
            [
                'name' => 'Kobelco SK210',
                'type' => MachineType::EXCAVATOR,
            ],
            [
                'name' => 'Caterpillar 323',
                'type' => MachineType::EXCAVATOR,
            ],
            [
                'name' => 'Hitachi ZX250',
                'type' => MachineType::EXCAVATOR,
            ],
            [
                'name' => 'Komatsu PC240',
                'type' => MachineType::EXCAVATOR,
            ],
            [
                'name' => 'Volvo EC250E',
                'type' => MachineType::EXCAVATOR,
            ],
            [
                'name' => 'JCB JS300',
                'type' => MachineType::EXCAVATOR,
            ],

            /*
            |--------------------------------------------------------------------------
            | Trucks
            |--------------------------------------------------------------------------
            */

            [
                'name' => 'Mercedes Actros',
                'type' => MachineType::TRUCK,
            ],
            [
                'name' => 'MAN TGS',
                'type' => MachineType::TRUCK,
            ],
            [
                'name' => 'Volvo FMX',
                'type' => MachineType::TRUCK,
            ],
            [
                'name' => 'Scania R450',
                'type' => MachineType::TRUCK,
            ],
            [
                'name' => 'DAF XF',
                'type' => MachineType::TRUCK,
            ],
            [
                'name' => 'Iveco Trakker',
                'type' => MachineType::TRUCK,
            ],
            [
                'name' => 'Renault K',
                'type' => MachineType::TRUCK,
            ],
            [
                'name' => 'Ford F-Max',
                'type' => MachineType::TRUCK,
            ],

        ];

        foreach ($machines as $machine) {
            $machineModel = new Machine([
                'company_id' => $company->id,
                'name' => $machine['name'],
                'type' => $machine['type'],
                'status' => MachineStatus::ACTIVE,
                'image_path' => null,
            ]);

            $machineModel->owner()->associate($company);

            $machineModel->save();
        }
    }
}
