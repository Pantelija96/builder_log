<?php

namespace App\Console\Commands;

use App\Actions\DailyLog\LockDailyLogAction;
use App\Actions\MachineAssignment\CloseOpenMachineAssignmentsAction;
use App\Models\DailyLog;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use Illuminate\Console\Command;

class ClosePreviousDayCommand extends Command
{
    protected $signature = 'daily:close-previous-day';

    protected $description = 'Close and finalize the previous working day.';

    public function __construct(
        private readonly LockDailyLogAction $lockDailyLogAction,
        private readonly CloseOpenMachineAssignmentsAction $closeOpenMachineAssignmentsAction,
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

            /*
             * 1. Close open machine assignments for this date.
             */
            $this->closeOpenMachineAssignmentsAction->execute(
                date: $date,
            );

            /*
             * 2. Lock all unlocked DailyLogs for this date.
             */
            DailyLog::query()
                ->whereDate('date', $date)
                ->where('is_locked', false)
                ->chunkById(100, function ($dailyLogs) {
                    foreach ($dailyLogs as $dailyLog) {
                        $this->lockDailyLogAction->execute(
                            dailyLog: $dailyLog,
                        );
                    }
                });

            /*
             * 3. Find workers that worked on this date.
             */
            $workerIds = WorkerAttendance::query()
                ->whereDate('date', $date)
                ->distinct()
                ->pluck('worker_id');

            if ($workerIds->isEmpty()) {
                continue;
            }

            /*
             * 4. Make old workers available again,
             *    but ONLY if they don't already have an attendance today.
             *
             * This prevents recovery of an old DailyLog from accidentally
             * releasing a worker who is currently working today.
             */
            Worker::query()
                ->whereIn('id', $workerIds)
                ->whereDoesntHave('attendances', function ($query) use ($today) {
                    $query->whereDate('date', $today);
                })
                ->update([
                    'is_available' => true,
                ]);
        }

        return self::SUCCESS;
    }
}
