<?php

namespace App\Actions\SubcontractorLog;

use App\Actions\BaseAction;
use App\Enums\LogEvent;
use App\Models\SubcontractorLog;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use App\Traits\EnsuresDailyLogIsEditable;
use App\Traits\EnsuresWorkerCanManageDailyLog;

class DeleteSubcontractorLogAction extends BaseAction
{
    use EnsuresDailyLogIsEditable;
    use EnsuresWorkerCanManageDailyLog;

    public function __construct(
        private readonly LoggingService $logging,
    ) {
    }

    public function execute(SubcontractorLog $subcontractorLog, Worker $currentWorker, string $reason,): void {

        $dailyLog = $subcontractorLog->dailyLog;

        $this->ensureEditable($dailyLog);
        $this->ensureCanModify($dailyLog, $currentWorker);

        $this->transaction(function () use (
            $subcontractorLog,
            $currentWorker,
            $reason
        ) {

            $this->logging->audit(
                actor: $currentWorker,
                subject: $subcontractorLog,
                event: LogEvent::SUBCONTRACTOR_LOG_DELETED,
                oldValues: $subcontractorLog->getRawOriginal(),
                reason: $reason,
            );

            $subcontractorLog->forceDelete();
        });
    }
}
