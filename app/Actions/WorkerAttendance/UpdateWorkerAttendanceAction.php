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
use Illuminate\Support\Facades\DB;

class UpdateWorkerAttendanceAction extends BaseAction
{
    use EnsuresDailyLogIsEditable;
    use EnsuresWorkerCanManageDailyLog;

    public function __construct(
        private readonly LoggingService $logging,
    ) {}

    public function execute(DailyLog $dailyLog, WorkerAttendance $workerAttendance, UpdateWorkerAttendanceData $data, Worker $currentWorker,): WorkerAttendance {
        $this->ensureEditable($dailyLog);
        $this->ensureCanModify($dailyLog, $currentWorker);

        if ($workerAttendance->daily_log_id !== $dailyLog->id) {
            throw new BusinessException(
                'Worker attendance does not belong to the specified daily log.'
            );
        }

        if ($data->startedAt !== null && !$data->startedAt->isSameDay($workerAttendance->date)) {
            throw new BusinessException('Started time must belong to the daily log date.');
        }

        if ($data->finishedAt !== null && !$data->finishedAt->isSameDay($workerAttendance->date)) {
            throw new BusinessException('Finished time must belong to the daily log date.');
        }

        return $this->transaction(function () use ($workerAttendance, $data, $currentWorker) {

            $updateData = $data->toUpdateArray();

            if (!empty($updateData)) {
                $workerAttendance->update($updateData);
            }

            $this->logging->activity(
                actor: $currentWorker,
                subject: $workerAttendance,
                event: LogEvent::WORKER_ATTENDANCE_UPDATED,
            );

            return $workerAttendance->refresh();
        });
    }
}
