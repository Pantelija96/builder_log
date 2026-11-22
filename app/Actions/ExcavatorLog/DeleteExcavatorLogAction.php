<?php

namespace App\Actions\ExcavatorLog;

use App\Actions\BaseAction;
use App\Enums\LogEvent;
use App\Models\ExcavatorLog;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\Services\Logging\LoggingService;
use App\Traits\ManagesMachineAssignments;

class DeleteExcavatorLogAction extends BaseAction
{
    use ManagesMachineAssignments;

    public function __construct(
        private readonly LoggingService $logging,
    ) {}

    public function execute(
        ExcavatorLog $excavatorLog,
        Worker $currentWorker,
        string $reason,
    ): void {
        $this->transaction(function () use (
            $excavatorLog,
            $currentWorker,
            $reason,
        ) {
            $this->lockMachineAssignments($currentWorker);
            $excavatorLog = ExcavatorLog::query()->findOrFail($excavatorLog->id);
            $assignment = $excavatorLog->machineAssignment;
            abort_unless($assignment, 404);
            $this->editableMachineDailyLog($assignment, $currentWorker);
            $oldValues = $excavatorLog->getAttributes();

            $this->logging->activity(
                actor: $currentWorker,
                subject: $excavatorLog,
                event: LogEvent::EXCAVATOR_LOG_DELETED,
            );

            $this->logging->audit(
                actor: $currentWorker,
                subject: $excavatorLog,
                event: LogEvent::EXCAVATOR_LOG_DELETED,
                oldValues: $oldValues,
                reason: $reason,
            );

            $machineAssignment = $excavatorLog->machineAssignment;

            if ($machineAssignment) {
                WorkerAttendance::query()
                    ->where(
                        'machine_assignment_id',
                        $machineAssignment->id,
                    )
                    ->delete();
            }

            $excavatorLog->delete();

            $machineAssignment?->delete();
        });
    }
}
