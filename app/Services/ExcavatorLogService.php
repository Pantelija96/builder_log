<?php

namespace App\Services;

use App\Actions\ExcavatorLog\CreateExcavatorLogAction;
use App\Actions\ExcavatorLog\DeleteExcavatorLogAction;
use App\Actions\ExcavatorLog\UpdateExcavatorLogAction;
use App\Actions\MachineAssignment\CreateMachineAssignmentAction;
use App\DTO\ExcavatorLog\CreateExcavatorLogData;
use App\DTO\ExcavatorLog\UpdateExcavatorLogData;
use App\DTO\MachineAssignment\CreateMachineAssignmentForOperatorData;
use App\Models\ExcavatorLog;
use App\Models\Worker;

class ExcavatorLogService
{
    public function __construct(
        private readonly CreateExcavatorLogAction $createExcavatorLogAction,
        private readonly UpdateExcavatorLogAction $updateExcavatorLogAction,
        private readonly DeleteExcavatorLogAction $deleteExcavatorLogAction,
        private readonly CreateMachineAssignmentAction $createMachineAssignmentAction,
    ) {
    }

    public function create(
        CreateExcavatorLogData $data,
        Worker $currentWorker,
    ): ExcavatorLog {

        return $this->createExcavatorLogAction->execute(
            data: $data,
            currentWorker: $currentWorker,
        );
    }

    public function createForOperator(
        CreateMachineAssignmentForOperatorData $data,
        Worker $currentWorker,
    ): ExcavatorLog {

        $assignment = $this->createMachineAssignmentAction
            ->executeForOperator(
                data: $data,
                currentWorker: $currentWorker,
            );

        return $this->createExcavatorLogAction->execute(
            data: new CreateExcavatorLogData(
                machineAssignmentId: $assignment->id,
            ),
            currentWorker: $currentWorker,
        );
    }

    public function update(
        ExcavatorLog $excavatorLog,
        UpdateExcavatorLogData $data,
        Worker $currentWorker,
        ?string $reason = null,
    ): ExcavatorLog {

        $this->ensureCompanyAccess(
            excavatorLog: $excavatorLog,
            currentWorker: $currentWorker,
        );

        $this->ensureCanUpdate(
            excavatorLog: $excavatorLog,
            currentWorker: $currentWorker,
        );

        return $this->updateExcavatorLogAction->execute(
            excavatorLog: $excavatorLog,
            data: $data,
            currentWorker: $currentWorker,
            reason: $reason,
        );
    }

    public function delete(
        ExcavatorLog $excavatorLog,
        Worker $currentWorker,
        string $reason,
    ): void {

        $this->ensureCompanyAccess(
            excavatorLog: $excavatorLog,
            currentWorker: $currentWorker,
        );

        $this->ensureCanUpdate(
            excavatorLog: $excavatorLog,
            currentWorker: $currentWorker,
        );

        $this->deleteExcavatorLogAction->execute(
            excavatorLog: $excavatorLog,
            currentWorker: $currentWorker,
            reason: $reason,
        );
    }

    private function ensureCompanyAccess(
        ExcavatorLog $excavatorLog,
        Worker $currentWorker,
    ): void {

        if (
            $excavatorLog->machineAssignment->company_id
            !== $currentWorker->company_id
        ) {
            abort(404);
        }
    }

    private function ensureCanUpdate(
        ExcavatorLog $excavatorLog,
        Worker $currentWorker,
    ): void {

        if ($currentWorker->isAdmin()) {
            return;
        }

        /*
         * Site Manager can manage logs belonging
         * to his own machine assignments.
         */
        if (
            $currentWorker->isSiteManager()
            && $excavatorLog->machineAssignment->site_manager_id
            === $currentWorker->id
        ) {
            return;
        }

        /*
         * Operator can manage his own excavator log.
         */
        if (
            $currentWorker->isOperator()
            && $excavatorLog->worker_id === $currentWorker->id
        ) {
            return;
        }

        abort(403);
    }
}
