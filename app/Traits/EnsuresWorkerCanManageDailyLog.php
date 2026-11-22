<?php

namespace App\Traits;

use App\Enums\WorkerRole;
use App\Exceptions\BusinessException;
use App\Models\DailyLog;
use App\Models\Worker;

trait EnsuresWorkerCanManageDailyLog
{
    protected function ensureCanLock(DailyLog $dailyLog, ?Worker $worker,): void {
        if ($this->canManageAsSystemOrAdmin($worker) || $this->isDailyLogOwner($dailyLog, $worker)) {
            return;
        }

        throw new BusinessException(
            'You are not allowed to lock this daily log.'
        );
    }

    protected function ensureCanUnlock(DailyLog $dailyLog, ?Worker $worker,): void {
        if ($this->canManageAsSystemOrAdmin($worker)) {
            return;
        }

        throw new BusinessException(
            'You are not allowed to unlock this daily log.'
        );
    }

    protected function ensureCanModify(DailyLog $dailyLog, ?Worker $worker,): void {
        if ($this->canManageAsSystemOrAdmin($worker) || $this->isDailyLogOwner($dailyLog, $worker)) {
            return;
        }

        throw new BusinessException(
            'You are not allowed to modify this daily log.'
        );
    }

    private function canManageAsSystemOrAdmin(?Worker $worker): bool
    {
        return $worker === null || $worker->role === WorkerRole::ADMIN;
    }

    private function isDailyLogOwner(DailyLog $dailyLog, Worker $worker): bool
    {
        return $dailyLog->site_manager_id === $worker->id;
    }
}
