<?php

namespace App\Actions\WorkerAttendance;

use App\DTO\WorkerAttendance\CreateWorkerAttendanceData;
use App\Enums\LogEvent;
use App\Exceptions\BusinessException;
use App\Models\DailyLog;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\Services\Logging\LoggingService;
use App\Traits\EnsuresDailyLogIsEditable;
use App\Traits\EnsuresWorkerCanManageDailyLog;
use Illuminate\Database\UniqueConstraintViolationException;
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

        try {
            return DB::transaction(function () use ($dailyLog, $data, $currentWorker) {
                $worker = Worker::query()->where('company_id', $dailyLog->company_id)
                    ->lockForUpdate()->findOrFail($data->workerId);
                $this->ensureWorkerNotAlreadyAdded($dailyLog, $worker);

                $attendance = WorkerAttendance::create([
                    'company_id' => $dailyLog->company_id,
                    'daily_log_id' => $dailyLog->id,
                    'construction_site_id' => $dailyLog->construction_site_id,
                    'site_manager_id' => $currentWorker->id,
                    'worker_id' => $worker->id,
                    'date' => $dailyLog->date,
                    'ordinary_worker_day' => $worker->isWorker() ? $dailyLog->date->toDateString() : null,
                    'started_at' => $data->startedAt,
                    'finished_at' => $data->finishedAt,
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
        } catch (UniqueConstraintViolationException $exception) {
            if (str_contains($exception->getMessage(), 'worker_attendances_ordinary_day_unique')
                || str_contains($exception->getMessage(), 'worker_attendances.ordinary_worker_day')) {
                abort(409, 'Worker already has attendance for this date.');
            }

            throw $exception;
        }
    }

    private function ensureWorkerNotAlreadyAdded(DailyLog $dailyLog, Worker $worker): void
    {
        $attendances = WorkerAttendance::query()
            ->where('company_id', $dailyLog->company_id)
            ->where('worker_id', $worker->id);

        if ((clone $attendances)->whereBelongsTo($dailyLog)->exists()) {
            throw new BusinessException('Worker is already added to this daily log.');
        }

        abort_if($worker->isWorker() && $attendances->whereNull('machine_assignment_id')
            ->whereDate('date', $dailyLog->date)->exists(), 409, 'Worker already has attendance for this date.');
    }
}
