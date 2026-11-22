<?php

namespace App\Actions\WorkerAttendance;

use App\Actions\BaseAction;
use App\Enums\LogEvent;
use App\Models\DailyLog;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\Services\Logging\LoggingService;
use App\Traits\EnsuresDailyLogIsEditable;
use App\Traits\EnsuresWorkerCanManageDailyLog;

class DeleteWorkerAttendanceAction extends BaseAction
{
    use EnsuresWorkerCanManageDailyLog;
    use EnsuresDailyLogIsEditable;

    public function __construct(
        private readonly LoggingService $logging,
    ) {}

    public function execute(DailyLog $dailyLog, WorkerAttendance $workerAttendance, Worker $currentWorker, string $reason): void {
        $this->ensureCanModify($dailyLog, $currentWorker);
        $this->ensureDailyLogChild($dailyLog, $workerAttendance);
        $this->ensureEditable($dailyLog);

        $this->transaction(function () use ($workerAttendance, $currentWorker, $reason) {
            $worker = Worker::withTrashed()->where('company_id', $workerAttendance->company_id)
                ->lockForUpdate()->findOrFail($workerAttendance->worker_id);

            $oldValues = $workerAttendance->getAttributes();

            $this->logging->activity(actor: $currentWorker, subject: $workerAttendance, event: LogEvent::WORKER_ATTENDANCE_DELETED,);
            $this->logging->audit(actor: $currentWorker, subject: $workerAttendance, event: LogEvent::WORKER_ATTENDANCE_DELETED, oldValues: $oldValues, reason: $reason,);

            $workerAttendance->forceDelete();

            $hasRemainingAttendance = $workerAttendance->machine_assignment_id === null
                && $worker->isWorker() && $worker->attendances()
                ->where('company_id', $worker->company_id)
                ->where(fn ($query) => $query->whereDate('date', $workerAttendance->date)->orWhereDate('date', today()))
                ->exists();

            if (! $worker->trashed() && ! $hasRemainingAttendance) {
                $worker->update(['is_available' => true]);
            }
        });
    }
}
