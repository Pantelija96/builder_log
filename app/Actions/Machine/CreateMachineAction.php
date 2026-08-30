<?php

namespace App\Actions\Machine;

use App\Actions\BaseAction;
use App\DTO\Machine\CreateMachineData;
use App\Enums\MachineType;
use App\Models\Excavator;
use App\Models\Machine;
use App\Models\Truck;
use App\Models\Worker;

class CreateMachineAction extends BaseAction
{
    public function execute(
        CreateMachineData $data,
        Worker $currentWorker,
    ): Machine {
        return $this->transaction(function () use (
            $data,
            $currentWorker,
        ) {
            $machine = Machine::create([
                'company_id' => $currentWorker->company_id,
                'name' => $data->name,
                'type' => $data->type,
                'owner_type' => $data->ownerType,
                'owner_id' => $data->ownerId,
                'status' => $data->status,
                'image_path' => $data->imagePath,
                'license_plate' => $data->licensePlate,
            ]);

            if ($data->type === MachineType::EXCAVATOR) {
                Excavator::create([
                    'machine_id' => $machine->id,
                    'initial_work_hours' => $data->initialWorkHours,
                    'total_work_hours' => $data->initialWorkHours,
                ]);
            }

            if ($data->type === MachineType::TRUCK) {
                Truck::create([
                    'machine_id' => $machine->id,
                    'initial_mileage' => $data->initialMileage,
                ]);
            }

            return $machine->fresh([
                'owner',
                'excavator',
                'truck',
            ]);
        });
    }
}
