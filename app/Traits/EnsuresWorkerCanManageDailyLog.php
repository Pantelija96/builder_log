<?php

namespace App\Traits;

use App\Exceptions\BusinessException;
use App\Models\DailyLog;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Model;

trait EnsuresWorkerCanManageDailyLog
{
    protected function ensureCanLock(DailyLog $dailyLog, ?Worker $worker): void
    {
        if ($worker === null || $worker->isAdmin() || (
            $dailyLog->company_id === $worker->company_id && $this->isDailyManager($dailyLog, $worker)
        )) {
            return;
        }

        throw new BusinessException('You are not allowed to lock this daily log.');
    }

    protected function ensureCanUnlock(DailyLog $dailyLog, ?Worker $worker): void
    {
        if ($worker === null || $worker->isAdmin()) {
            return;
        }

        throw new BusinessException('You are not allowed to unlock this daily log.');
    }

    protected function ensureCanRead(DailyLog $dailyLog, Worker $worker): void
    {
        abort_unless($dailyLog->company_id === $worker->company_id, 404);
        abort_if($worker->isSiteManager() && ! $this->isDailyManager($dailyLog, $worker), 403);
    }

    protected function ensureCanModify(DailyLog $dailyLog, Worker $worker): void
    {
        abort_unless($dailyLog->company_id === $worker->company_id, 404);
        abort_unless($worker->isAdmin() || $this->isDailyManager($dailyLog, $worker), 403);
    }

    protected function ensureDailyLogChild(DailyLog $dailyLog, Model $child): void
    {
        abort_unless($child->company_id === $dailyLog->company_id && $child->daily_log_id === $dailyLog->id, 404);
    }

    protected function ensureManagerAttribution(Worker $worker): void
    {
        abort_if($worker->isAdmin(), 422, 'Use a site manager account to create this record. No manager attribution is accepted by this endpoint.');
    }

    private function isDailyManager(DailyLog $dailyLog, Worker $worker): bool
    {
        return $worker->isSiteManager()
            && $worker->participatedDailyLogs()->whereKey($dailyLog->id)->exists();
    }
}
