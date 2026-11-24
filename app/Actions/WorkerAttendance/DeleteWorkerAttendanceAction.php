<?php

namespace App\Actions\WorkerAttendance;

use App\Actions\BaseAction;
use App\Enums\LogEvent;
use App\Models\DailyLog;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\QueryFilters\AttendancePeriod;
use App\Services\Logging\LoggingService;
use App\Traits\EnsuresDailyLogIsEditable;
use App\Traits\EnsuresWorkerCanManageDailyLog;

class DeleteWorkerAttendanceAction extends BaseAction
{
    use EnsuresDailyLogIsEditable;
    use EnsuresWorkerCanManageDailyLog;

    public function __construct(
        private readonly LoggingService $logging,
    ) {}

    public function execute(DailyLog $dailyLog, WorkerAttendance $workerAttendance, Worker $currentWorker, string $reason): void
    {
        $this->ensureCanModify($dailyLog, $currentWorker);
        $this->ensureDailyLogChild($dailyLog, $workerAttendance);
        $this->ensureEditable($dailyLog);

        $this->transaction(function () use ($workerAttendance, $currentWorker, $reason) {
            $worker = AttendancePeriod::lockWorker($workerAttendance->company_id, $workerAttendance->worker_id);
            $workerAttendance = WorkerAttendance::query()->findOrFail($workerAttendance->id);

            $oldValues = $workerAttendance->getAttributes();

            $this->logging->activity(actor: $currentWorker, subject: $workerAttendance, event: LogEvent::WORKER_ATTENDANCE_DELETED);
            $this->logging->audit(actor: $currentWorker, subject: $workerAttendance, event: LogEvent::WORKER_ATTENDANCE_DELETED, oldValues: $oldValues, reason: $reason);

            $workerAttendance->forceDelete();

            AttendancePeriod::syncAvailability($worker);
        });
    }
}
