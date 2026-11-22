<?php

namespace App\Actions\SubcontractorLog;

use App\Actions\BaseAction;
use App\DTO\SubcontractorLog\UpdateSubcontractorLogData;
use App\Enums\LogEvent;
use App\Models\DailyLog;
use App\Models\SubcontractorLog;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use App\Traits\EnsuresDailyLogIsEditable;
use App\Traits\EnsuresWorkerCanManageDailyLog;

class UpdateSubcontractorLogAction extends BaseAction
{
    use EnsuresDailyLogIsEditable;
    use EnsuresWorkerCanManageDailyLog;

    public function __construct(private readonly LoggingService $logging,) {
    }

    public function execute(DailyLog $dailyLog, SubcontractorLog $subcontractorLog, UpdateSubcontractorLogData $data, Worker $currentWorker, string $reason,): SubcontractorLog {

        $this->ensureCanModify($dailyLog, $currentWorker);
        $this->ensureDailyLogChild($dailyLog, $subcontractorLog);
        $this->ensureEditable($dailyLog);

        return $this->transaction(function () use ($subcontractorLog, $data, $currentWorker, $reason) {

            $oldValues = $subcontractorLog->getOriginal();

            $subcontractorLog->update([
                'worker_count' => $data->workerCount,
                'started_at' => $data->startedAt,
                'finished_at' => $data->finishedAt,
                'note' => $data->note,
            ]);

            $this->logging->audit(
                actor: $currentWorker,
                subject: $subcontractorLog,
                event: LogEvent::SUBCONTRACTOR_LOG_UPDATED,
                oldValues: $oldValues,
                newValues: $subcontractorLog->fresh()->getAttributes(),
                reason: $reason,
            );

            return $subcontractorLog->fresh([
                'subcontractor',
                'creator',
                'siteManager',
            ]);
        });
    }
}
