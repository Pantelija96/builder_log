<?php

namespace App\Actions\TruckLog;

use App\Actions\BaseAction;
use App\DTO\TruckLog\CreateTruckLogData;
use App\Exceptions\BusinessException;
use App\Models\Machine;
use App\Models\TruckLog;
use App\Models\Worker;

class CreateTruckLogAction extends BaseAction
{
    public function execute(
        CreateTruckLogData $data,
        Worker $currentWorker,
        ?int $workerId = null,
    ): TruckLog {
        return $this->transaction(function () use (
            $data,
            $currentWorker,
            $workerId,
        ) {
            $machine = Machine::query()
                ->where(
                    'company_id',
                    $currentWorker->company_id,
                )
                ->whereKey($data->machineId)
                ->first();

            if (! $machine) {
                throw new BusinessException(
                    __('Machine not found.')
                );
            }

            if (! $machine->isTruck()) {
                throw new BusinessException(
                    __('Selected machine is not a truck.')
                );
            }

            if (! $machine->isActive()) {
                throw new BusinessException(
                    __('Truck is not active.')
                );
            }

            $workerId ??= $data->workerId;

            if (! $workerId) {
                throw new BusinessException(
                    __('Worker is required.')
                );
            }

            $worker = Worker::query()
                ->where(
                    'company_id',
                    $currentWorker->company_id,
                )
                ->whereKey($workerId)
                ->first();

            if (! $worker) {
                throw new BusinessException(
                    __('Worker not found.')
                );
            }

            $workerAlreadyHasTruck = TruckLog::query()
                ->where(
                    'worker_id',
                    $worker->id,
                )
                ->whereDate(
                    'date',
                    $data->date,
                )
                ->exists();

            if ($workerAlreadyHasTruck) {
                throw new BusinessException(
                    __('Worker already has a truck for this date.')
                );
            }

            $truckHasOpenSession = TruckLog::query()
                ->where(
                    'machine_id',
                    $machine->id,
                )
                ->whereDate(
                    'date',
                    $data->date,
                )
                ->whereNull('operator_finished_at')
                ->exists();

            if ($truckHasOpenSession) {
                throw new BusinessException(
                    __('Truck is currently assigned to another worker.')
                );
            }

            $truckLog = TruckLog::create([
                'machine_id' => $machine->id,
                'worker_id' => $worker->id,
                'created_by' => $currentWorker->id,
                'company_id' => $currentWorker->company_id,
                'date' => $data->date,

                'site_manager_started_at' => null,
                'site_manager_finished_at' => null,

                'operator_started_at' => null,
                'operator_finished_at' => null,

                'start_mileage' => null,
                'end_mileage' => null,

                'fuel_added' => 0,
                'fuel_remaining' => null,

                'note' => null,
            ]);

            return $truckLog->fresh([
                'machine',
                'worker',
                'creator',
            ]);
        });
    }
}
