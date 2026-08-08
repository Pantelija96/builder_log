<?php

namespace App\Actions\MachineAssignment;

use App\Enums\LogEvent;
use App\Models\MachineAssignment;
use App\Models\Worker;
use App\Services\Logging\LoggingService;

class DeleteMachineAssignmentAction
{
    public function __construct(
        private readonly LoggingService $logging,
    ) {
    }

    public function execute(
        MachineAssignment $assignment,
        Worker $currentWorker,
        string $reason,
    ): void {
        $oldValues = $assignment->getAttributes();

        $this->logging->activity(
            actor: $currentWorker,
            subject: $assignment,
            event: LogEvent::MACHINE_ASSIGNMENT_DELETED,
        );

        $this->logging->audit(
            actor: $currentWorker,
            subject: $assignment,
            event: LogEvent::MACHINE_ASSIGNMENT_DELETED,
            oldValues: $oldValues,
            reason: $reason,
        );

        $assignment->delete();
    }
}
