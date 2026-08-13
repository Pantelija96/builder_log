<?php

namespace App\Actions\Machine;

use App\Actions\BaseAction;
use App\DTO\Machine\CreateMachineData;
use App\Models\Machine;
use App\Models\Worker;

class CreateMachineAction extends BaseAction
{
    public function execute(CreateMachineData $data, Worker $currentWorker,): Machine
    {
        return $this->transaction(function () use (
            $data,
            $currentWorker,
        )
        {
            $machine = Machine::create([
                'company_id' => $currentWorker->company_id,
                'name' => $data->name,
                'type' => $data->type,
                'owner_type' => $data->ownerType,
                'owner_id' => $data->ownerId,
                'status' => $data->status,
                'image_path' => $data->imagePath,
            ]);

            return $machine->fresh([
                'owner',
            ]);
        });
    }
}
