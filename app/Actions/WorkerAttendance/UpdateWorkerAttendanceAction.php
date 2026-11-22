<?php

namespace App\Actions\WorkerAttendance;

use App\Actions\BaseAction;
use App\DTO\WorkerAttendance\UpdateWorkerAttendanceData;
use App\Enums\LogEvent;
use App\Exceptions\BusinessException;
use App\Models\DailyLog;
use App\Models\Worker;
use App\Models\WorkerAttendance;
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

    public function execute(DailyLog $dailyLog, WorkerAttendance $workerAttendance, UpdateWorkerAttendanceData $data, Worker $currentWorker,): WorkerAttendance {
        $this->ensureCanModify($dailyLog, $currentWorker);
        $this->ensureDailyLogChild($dailyLog, $workerAttendance);
        $this->ensureEditable($dailyLog);

        $this->ensureTimesMatchAttendanceDate($workerAttendance, $data);

        return $this->transaction(function () use ($workerAttendance, $data, $currentWorker) {

            $oldValues = $workerAttendance->getAttributes();
            $updateData = $data->toUpdateArray();

            if (!empty($updateData)) {
                $workerAttendance->update($updateData);
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

    private function ensureTimesMatchAttendanceDate(WorkerAttendance $attendance, UpdateWorkerAttendanceData $data): void
    {
        if ($data->startedAt !== null && ! $data->startedAt->isSameDay($attendance->date)) {
            throw new BusinessException('Started time must belong to the daily log date.');
        }

        if ($data->finishedAt !== null && ! $data->finishedAt->isSameDay($attendance->date)) {
            throw new BusinessException('Finished time must belong to the daily log date.');
        }
    }
}
