<?php

namespace App\Actions\DailyLog;

use App\Actions\MachineAssignment\CloseOpenMachineAssignmentsAction;
use App\Models\DailyLog;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use Carbon\CarbonInterface;

class CloseDailyLogsForDateAction
{
    public function __construct(
        private readonly LockDailyLogAction $lockDailyLogAction,
        private readonly CloseOpenMachineAssignmentsAction $closeOpenMachineAssignmentsAction,
    ) {}

    public function execute(CarbonInterface $date, CarbonInterface $today): void
    {
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
            return;
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
}
