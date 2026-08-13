<?php

namespace App\Actions\Machine;

use App\Actions\BaseAction;
use App\DTO\Machine\UpdateMachineData;
use App\Models\Machine;
use App\Models\Worker;

class UpdateMachineAction extends BaseAction
{
    public function execute(Machine $machine, UpdateMachineData $data, Worker $currentWorker,): Machine
    {
        return $this->transaction(function () use (
            $machine,
            $data,
            $currentWorker,
        )
        {

            $machine->update([
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
