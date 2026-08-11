<?php

namespace App\Actions\ExcavatorLog;

use App\Enums\LogEvent;
use App\Models\ExcavatorLog;
use App\Models\Worker;
use App\Services\Logging\LoggingService;

class DeleteExcavatorLogAction
{
    public function __construct(
        private readonly LoggingService $logging,
    ) {
    }

    public function execute(
        ExcavatorLog $excavatorLog,
        Worker $currentWorker,
        string $reason,
    ): void {

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

        $excavatorLog->delete();
    }
}
