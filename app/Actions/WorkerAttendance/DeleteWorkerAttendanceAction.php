<?php

namespace App\Actions\WorkerAttendance;

use App\Actions\BaseAction;
use App\Enums\LogEvent;
use App\Exceptions\BusinessException;
use App\Models\DailyLog;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\Services\Logging\LoggingService;
use App\Traits\EnsuresDailyLogIsEditable;
use App\Traits\EnsuresWorkerCanManageDailyLog;
use Illuminate\Support\Facades\DB;

class DeleteWorkerAttendanceAction extends BaseAction
{
    use EnsuresWorkerCanManageDailyLog;
    use EnsuresDailyLogIsEditable;

    public function __construct(
        private readonly LoggingService $logging,
    ) {}

    public function execute(DailyLog $dailyLog, WorkerAttendance $workerAttendance, Worker $currentWorker, string $reason): void {
        if ($workerAttendance->daily_log_id !== $dailyLog->id) {
            throw new BusinessException(
                'Worker attendance does not belong to the specified daily log.'
            );
        }

        $this->ensureCanModify($dailyLog, $currentWorker);
        $this->ensureEditable($dailyLog);

        $this->transaction(function () use ($workerAttendance, $currentWorker, $reason,) {

            $oldValues = $workerAttendance->getAttributes();

            $this->logging->activity(actor: $currentWorker, subject: $workerAttendance, event: LogEvent::WORKER_ATTENDANCE_DELETED,);
            $this->logging->audit(actor: $currentWorker, subject: $workerAttendance, event: LogEvent::WORKER_ATTENDANCE_DELETED, oldValues: $oldValues, reason: $reason,);

            $workerAttendance->forceDelete();

            Worker::query()
                ->whereKey($workerAttendance->worker_id)
                ->update([
                    'is_available' => true,
                ]);
        });
    }
}
