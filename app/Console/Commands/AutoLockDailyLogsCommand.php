<?php

namespace App\Console\Commands;

use App\Actions\DailyLog\LockDailyLogAction;
use App\Models\DailyLog;
use Illuminate\Console\Command;

class AutoLockDailyLogsCommand extends Command
{
    protected $signature = 'daily-logs:auto-lock';

    protected $description = 'Automatically lock daily logs from previous days.';

    public function __construct(
        private readonly LockDailyLogAction $lockDailyLogAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        DailyLog::query()
            ->where('is_locked', false)
            ->whereDate('date', '<', today())
            ->chunkById(100, function ($dailyLogs) {
                foreach ($dailyLogs as $dailyLog) {
                    $this->lockDailyLogAction->execute($dailyLog);
                }
            });

        return self::SUCCESS;
    }
}
