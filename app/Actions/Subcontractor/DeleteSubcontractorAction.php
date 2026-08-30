<?php

namespace App\Actions\Subcontractor;

use App\Models\Subcontractor;
use App\Models\Worker;
use App\Enums\LogEvent;
use App\Services\Logging\LoggingService;

class DeleteSubcontractorAction
{
    public function __construct(
        private readonly LoggingService $logging,
    ) {
    }

    public function execute(
        Subcontractor $subcontractor,
        Worker $currentWorker,
        string $reason,
    ): void {
        $oldValues = $subcontractor->getAttributes();

        $this->logging->activity(
            actor: $currentWorker,
            subject: $subcontractor,
            event: LogEvent::SUBCONTRACTOR_DELETED,
        );

        $this->logging->audit(
            actor: $currentWorker,
            subject: $subcontractor,
            event: LogEvent::SUBCONTRACTOR_DELETED,
            oldValues: $oldValues,
            reason: $reason,
        );

        $subcontractor->delete();
    }
}
