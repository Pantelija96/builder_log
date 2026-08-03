<?php

namespace App\Traits;

use App\Enums\WorkerRole;
use App\Exceptions\BusinessException;
use App\Models\DailyLog;
use App\Models\Worker;

trait EnsuresWorkerCanManageDailyLog
{
    protected function ensureCanLock(DailyLog $dailyLog, ?Worker $worker,): void {
        if ($worker === null) {
            return;
        }

        if ($worker->role === WorkerRole::ADMIN) {
            return;
        }

        if ($dailyLog->site_manager_id === $worker->id) {
            return;
        }

        throw new BusinessException(
            'You are not allowed to lock this daily log.'
        );
    }

    protected function ensureCanUnlock(DailyLog $dailyLog, ?Worker $worker,): void {
        if ($worker === null) {
            return;
        }

        if ($worker->role === WorkerRole::ADMIN) {
            return;
        }

        throw new BusinessException(
            'You are not allowed to unlock this daily log.'
        );
    }

    protected function ensureCanModify(DailyLog $dailyLog, ?Worker $worker,): void {
        if ($worker === null) {
            return;
        }

        if ($worker->role === WorkerRole::ADMIN) {
            return;
        }

        if ($dailyLog->site_manager_id === $worker->id) {
            return;
        }

        throw new BusinessException(
            'You are not allowed to modify this daily log.'
        );
    }
}
