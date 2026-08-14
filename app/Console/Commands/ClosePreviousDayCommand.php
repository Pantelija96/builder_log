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
        $date = today()->subDay();

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

        $this->closeOpenMachineAssignmentsAction->execute(
            date: $date,
        );


        $workerIds = WorkerAttendance::query()
            ->whereDate('date', $date)
            ->distinct()
            ->pluck('worker_id');

        if ($workerIds->isNotEmpty()) {
            Worker::query()
                ->whereIn('id', $workerIds)
                ->update([
                    'is_available' => true,
                ]);
        }

        return self::SUCCESS;
    }
}
