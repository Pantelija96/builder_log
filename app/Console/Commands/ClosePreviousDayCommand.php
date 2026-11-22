<?php

namespace App\Console\Commands;

use App\Actions\DailyLog\CloseDailyLogsForDateAction;
use App\Models\DailyLog;
use Illuminate\Console\Command;

class ClosePreviousDayCommand extends Command
{
    protected $signature = 'daily:close-previous-day';

    protected $description = 'Close and finalize the previous working day.';

    public function __construct(
        private readonly CloseDailyLogsForDateAction $closeDailyLogsForDateAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $today = today();

        /*
         * Find all previous dates that have DailyLogs.
         *
         * This allows the command to recover automatically if the scheduler
         * did not run for one or more days.
         */
        $dates = DailyLog::query()
            ->whereDate('date', '<', $today)
            ->select('date')
            ->distinct()
            ->orderBy('date')
            ->pluck('date');

        foreach ($dates as $date) {
            $this->closeDailyLogsForDateAction->execute($date, $today);
        }

        return self::SUCCESS;
    }
}
