<?php

namespace App\Actions\Machine;

use App\Enums\LogEvent;
use App\Models\Machine;
use App\Models\Worker;
use App\Services\Logging\LoggingService;

class DeleteMachineAction
{
    public function __construct(
        private readonly LoggingService $logging,
    ) {
    }

    public function execute(
        Machine $machine,
        Worker $currentWorker,
        string $reason,
    ): void {
        $oldValues = $machine->getAttributes();

        $this->logging->activity(
            actor: $currentWorker,
            subject: $machine,
            event: LogEvent::MACHINE_DELETED,
        );

        $this->logging->audit(
            actor: $currentWorker,
            subject: $machine,
            event: LogEvent::MACHINE_DELETED,
            oldValues: $oldValues,
            reason: $reason,
        );

        $machine->delete();
    }
}
