<?php

namespace App\Actions\WorkerAttendance;

use App\Actions\BaseAction;
use App\DTO\WorkerAttendance\CreateWorkerAttendanceData;
use App\Enums\LogEvent;
use App\Exceptions\BusinessException;
use App\Models\DailyLog;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\Services\Logging\LoggingService;
use App\Traits\EnsuresDailyLogIsEditable;
use App\Traits\EnsuresWorkerCanManageDailyLog;

class CreateWorkerAttendanceAction extends BaseAction
{
    use EnsuresDailyLogIsEditable;
    use EnsuresWorkerCanManageDailyLog;

    public function __construct(
        private readonly LoggingService $logging,
    ) {}

    public function execute(DailyLog $dailyLog, CreateWorkerAttendanceData $data, Worker $currentWorker,): WorkerAttendance {

        $this->ensureEditable($dailyLog);
        $this->ensureCanModify($dailyLog, $currentWorker,);
        $this->ensureWorkerNotAlreadyAdded($dailyLog, $data->workerId,);

        return $this->transaction(function () use ($dailyLog, $data, $currentWorker,) {

            $attendance = WorkerAttendance::create([
                'company_id' => $dailyLog->company_id,
                'daily_log_id' => $dailyLog->id,
                'construction_site_id' => $dailyLog->construction_site_id,
                'site_manager_id' => $dailyLog->site_manager_id,
                'worker_id' => $data->workerId,
                'date' => $dailyLog->date,
                'started_at' => $data->startedAt,
                'finished_at' => $data->finishedAt,
                'advance_payment' => $data->advancePayment,
                'created_by' => $currentWorker->id,
            ])->refresh();

            $this->logging->activity(
                actor: $currentWorker,
                subject: $attendance,
                event: LogEvent::WORKER_ATTENDANCE_CREATED,
            );

            return $attendance;
        });
    }

    private function ensureWorkerNotAlreadyAdded(DailyLog $dailyLog, int $workerId,): void {

        if (
            WorkerAttendance::query()
                ->where('daily_log_id', $dailyLog->id)
                ->where('worker_id', $workerId)
                ->exists()
        ) {
            throw new BusinessException(
                'Worker is already added to this daily log.'
            );
        }
    }
}
