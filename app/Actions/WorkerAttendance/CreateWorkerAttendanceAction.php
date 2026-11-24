<?php

namespace App\Actions\WorkerAttendance;

use App\DTO\WorkerAttendance\CreateWorkerAttendanceData;
use App\Enums\LogEvent;
use App\Models\DailyLog;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\QueryFilters\AttendancePeriod;
use App\Services\Logging\LoggingService;
use App\Traits\EnsuresDailyLogIsEditable;
use App\Traits\EnsuresWorkerCanManageDailyLog;
use Illuminate\Support\Facades\DB;

class CreateWorkerAttendanceAction
{
    use EnsuresDailyLogIsEditable;
    use EnsuresWorkerCanManageDailyLog;

    public function __construct(
        private readonly LoggingService $logging,
    ) {}

    public function execute(DailyLog $dailyLog, CreateWorkerAttendanceData $data, Worker $currentWorker): WorkerAttendance
    {
        $this->ensureCanModify($dailyLog, $currentWorker);
        $this->ensureEditable($dailyLog);
        $this->ensureManagerAttribution($currentWorker);

        return DB::transaction(function () use ($dailyLog, $data, $currentWorker) {
            $worker = AttendancePeriod::lockWorker($dailyLog->company_id, $data->workerId);
            abort_if($worker->trashed(), 404);
            $start = AttendancePeriod::local($data->startedAt);
            $finish = $data->finishedAt === null ? null : AttendancePeriod::local($data->finishedAt);
            $end = AttendancePeriod::effectiveEnd($start, $finish, $dailyLog->date->toDateString());
            abort_if(AttendancePeriod::hasConflict($worker, $start, $end), 409, 'Worker already has an overlapping period.');

            $attendance = WorkerAttendance::create([
                'company_id' => $dailyLog->company_id,
                'daily_log_id' => $dailyLog->id,
                'construction_site_id' => $dailyLog->construction_site_id,
                'site_manager_id' => $currentWorker->id,
                'worker_id' => $worker->id,
                'date' => $dailyLog->date,
                'started_at' => $start,
                'finished_at' => $finish,
                'advance_payment' => $data->advancePayment,
                'hourly_rate' => $data->hourlyRate ?? $worker->hourly_rate,
                'created_by' => $currentWorker->id,
            ])->refresh();

            $worker->update(['is_available' => false]);

            $this->logging->activity(
                actor: $currentWorker,
                subject: $attendance,
                event: LogEvent::WORKER_ATTENDANCE_CREATED,
            );

            return $attendance;
        }, attempts: 5);
    }
}
