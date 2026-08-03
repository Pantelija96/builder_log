<?php

namespace App\Traits;

use App\Exceptions\BusinessException;
use App\Models\DailyLog;

trait EnsuresDailyLogIsEditable
{
    protected function ensureEditable(DailyLog $dailyLog): void
    {
        if ($dailyLog->isLocked()) {
            throw new BusinessException(
                'Daily log is locked.'
            );
        }
    }
}
