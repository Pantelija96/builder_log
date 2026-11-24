<?php

namespace App\Actions\WorkerAttendance;

use App\Actions\BaseAction;
use App\DTO\WorkerAttendance\UpdateWorkerAttendanceData;
use App\Enums\LogEvent;
use App\Exceptions\BusinessException;
use App\Models\DailyLog;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\QueryFilters\AttendancePeriod;
use App\Services\Logging\LoggingService;
use App\Traits\EnsuresDailyLogIsEditable;
use App\Traits\EnsuresWorkerCanManageDailyLog;

class UpdateWorkerAttendanceAction extends BaseAction
{
    use EnsuresDailyLogIsEditable;
    use EnsuresWorkerCanManageDailyLog;

    public function __construct(
        private readonly LoggingService $logging,
    ) {}

    public function execute(DailyLog $dailyLog, WorkerAttendance $workerAttendance, UpdateWorkerAttendanceData $data, Worker $currentWorker): WorkerAttendance
    {
        $this->ensureCanModify($dailyLog, $currentWorker);
        $this->ensureDailyLogChild($dailyLog, $workerAttendance);
        $this->ensureEditable($dailyLog);

        return $this->transaction(function () use ($workerAttendance, $data, $currentWorker) {

            $worker = AttendancePeriod::lockWorker($workerAttendance->company_id, $workerAttendance->worker_id);
            $workerAttendance = WorkerAttendance::query()->findOrFail($workerAttendance->id);
            $oldValues = $workerAttendance->getAttributes();
            $updateData = $data->toUpdateArray();

            if (array_key_exists('started_at', $updateData) || array_key_exists('finished_at', $updateData)) {
                $start = AttendancePeriod::local($data->startedAt ?? $workerAttendance->started_at);
                if (array_key_exists('started_at', $updateData) && $workerAttendance->machine_assignment_id === null
                    && ! $start->isSameDay($workerAttendance->date)) {
                    throw new BusinessException('Started time must belong to the daily log date.');
                }

                $finish = array_key_exists('finished_at', $updateData) ? $data->finishedAt : $workerAttendance->finished_at;
                $finish = $finish === null ? null : AttendancePeriod::local($finish);
                $end = AttendancePeriod::effectiveEnd($start, $finish, $workerAttendance->date->toDateString());
                abort_if(AttendancePeriod::hasConflict($worker, $start, $end,
                    $workerAttendance->id, $workerAttendance->machine_assignment_id), 409, 'Worker already has an overlapping period.');
                $updateData['started_at'] = $start;
                $updateData['finished_at'] = $finish;
            }

            if ($updateData !== []) {
                $workerAttendance->update($updateData);
                AttendancePeriod::syncAvailability($worker);
            }

            $this->logging->activity(
                actor: $currentWorker,
                subject: $workerAttendance,
                event: LogEvent::WORKER_ATTENDANCE_UPDATED,
            );

            if ($workerAttendance->machine_assignment_id === null
                && $currentWorker->id !== $workerAttendance->created_by) {
                $this->logging->audit(
                    actor: $currentWorker,
                    subject: $workerAttendance,
                    event: LogEvent::WORKER_ATTENDANCE_UPDATED,
                    oldValues: $oldValues,
                );
            }

            return $workerAttendance->refresh();
        });
    }
}
