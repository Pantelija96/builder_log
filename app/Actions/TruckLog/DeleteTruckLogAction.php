<?php

namespace App\Actions\TruckLog;

use App\Actions\BaseAction;
use App\Enums\LogEvent;
use App\Models\TruckLog;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\Services\Logging\LoggingService;
use App\Traits\ManagesMachineAssignments;

class DeleteTruckLogAction extends BaseAction
{
    use ManagesMachineAssignments;

    public function __construct(
        private readonly LoggingService $logging,
    ) {}

    public function execute(
        TruckLog $truckLog,
        Worker $currentWorker,
        string $reason,
    ): void {
        $this->transaction(function () use (
            $truckLog,
            $currentWorker,
            $reason,
        ) {
            $this->lockMachineAssignments($currentWorker);
            $truckLog = TruckLog::query()->findOrFail($truckLog->id);
            $assignment = $truckLog->machineAssignment;
            abort_unless($assignment, 404);
            $this->editableMachineDailyLog($assignment, $currentWorker);
            $oldValues = $truckLog->getAttributes();

            $this->logging->activity(
                actor: $currentWorker,
                subject: $truckLog,
                event: LogEvent::TRUCK_LOG_DELETED,
            );

            $this->logging->audit(
                actor: $currentWorker,
                subject: $truckLog,
                event: LogEvent::TRUCK_LOG_DELETED,
                oldValues: $oldValues,
                reason: $reason,
            );

            $machineAssignment = $truckLog->machineAssignment;

            if ($machineAssignment) {
                WorkerAttendance::query()
                    ->where(
                        'machine_assignment_id',
                        $machineAssignment->id,
                    )
                    ->delete();
            }

            $truckLog->delete();

            $machineAssignment?->delete();
        });
    }
}
