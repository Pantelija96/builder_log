<?php

namespace App\Actions\TruckLog;

use App\Actions\BaseAction;
use App\Enums\LogEvent;
use App\Models\TruckLog;
use App\Models\Worker;
use App\Services\Logging\LoggingService;

class DeleteTruckLogAction
{
    public function __construct(
        private readonly LoggingService $logging,
    ) {
    }

    public function execute(
        TruckLog $truckLog,
        Worker $currentWorker,
        string $reason,
    ): void {

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

        $truckLog->delete();
    }
}
