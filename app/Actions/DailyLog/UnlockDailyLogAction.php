<?php

namespace App\Actions\DailyLog;

use App\Actions\BaseAction;
use App\Enums\LogEvent;
use App\Exceptions\BusinessException;
use App\Models\DailyLog;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use App\Traits\EnsuresWorkerCanManageDailyLog;

class UnlockDailyLogAction extends BaseAction
{
    use EnsuresWorkerCanManageDailyLog;

    public function __construct(
        private readonly LoggingService $logging,
    ) {
    }

    public function execute(DailyLog $dailyLog, ?string $reason, ?Worker $worker = null): DailyLog {
        if (! $dailyLog->isLocked()) {
            throw new BusinessException(
                'Daily log is not locked.'
            );
        }

        $this->ensureCanUnlock($dailyLog, $worker);

        return $this->transaction(function () use ($dailyLog, $worker, $reason) {
            $oldValues = $dailyLog->getOriginal();

            $dailyLog->update([
                'is_locked' => false,
                'locked_at' => null,
                'locked_by' => null,
            ]);

            $this->logging->activity(
                actor: $worker,
                subject: $dailyLog,
                event: LogEvent::DAILY_LOG_UNLOCKED,
            );

            $this->logging->audit(
                actor: $worker,
                subject: $dailyLog,
                event: LogEvent::DAILY_LOG_UNLOCKED,
                oldValues: $oldValues,
                reason: $reason,
            );

            return $dailyLog->refresh();
        });
    }
}
