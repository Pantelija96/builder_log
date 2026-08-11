<?php

namespace App\Actions\ExcavatorLog;

use App\Actions\BaseAction;
use App\DTO\ExcavatorLog\CreateExcavatorLogData;
use App\Enums\MachineType;
use App\Exceptions\BusinessException;
use App\Models\ExcavatorLog;
use App\Models\MachineAssignment;
use App\Models\Worker;

class CreateExcavatorLogAction extends BaseAction
{
    public function execute(
        CreateExcavatorLogData $data,
        Worker $currentWorker,
    ): ExcavatorLog {

        return $this->transaction(function () use (
            $data,
            $currentWorker,
        ) {

            $assignment = MachineAssignment::query()
                ->where(
                    'company_id',
                    $currentWorker->company_id,
                )
                ->whereKey($data->machineAssignmentId)
                ->with([
                    'machine',
                ])
                ->first();

            if (! $assignment) {
                throw new BusinessException(
                    __('Machine assignment not found.')
                );
            }

            if (
                ! $assignment->machine
                || $assignment->machine->type !== MachineType::EXCAVATOR
            ) {
                throw new BusinessException(
                    __('Machine assignment does not belong to an excavator.')
                );
            }

            if ($assignment->worker_id === null) {
                throw new BusinessException(
                    __('Machine assignment has no assigned operator.')
                );
            }

            if ($assignment->excavatorLog()->exists()) {
                throw new BusinessException(
                    __('Excavator log already exists for this machine assignment.')
                );
            }

            $excavatorLog = ExcavatorLog::create([
                'machine_assignment_id' => $assignment->id,
                'worker_id' => $assignment->worker_id,
                'created_by' => $currentWorker->id,

                'site_manager_started_at' => null,
                'site_manager_finished_at' => null,

                'operator_started_at' => null,
                'operator_finished_at' => null,

                'work_hours' => 0,
                'fuel_added' => 0,
                'fuel_remaining' => null,
                'note' => null,
            ]);

            return $excavatorLog->fresh([
                'machineAssignment',
                'worker',
                'creator',
            ]);
        });
    }
}
