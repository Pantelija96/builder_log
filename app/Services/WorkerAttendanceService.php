<?php

namespace App\Services;

use App\Actions\WorkerAttendance\CreateWorkerAttendanceAction;
use App\Actions\WorkerAttendance\DeleteWorkerAttendanceAction;
use App\Actions\WorkerAttendance\UpdateWorkerAttendanceAction;
use App\DTO\WorkerAttendance\CreateWorkerAttendanceData;
use App\DTO\WorkerAttendance\GetWorkerAttendancesData;
use App\DTO\WorkerAttendance\UpdateWorkerAttendanceData;
use App\Models\DailyLog;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\QueryFilters\WorkerAttendanceFilter;
use Illuminate\Database\Eloquent\Collection;

class WorkerAttendanceService
{
    private function query(DailyLog $dailyLog)
    {
        return WorkerAttendance::query()
            ->whereBelongsTo($dailyLog)
            ->with([
                'worker',
                'creator',
            ]);
    }

    public function __construct(
        private readonly CreateWorkerAttendanceAction $createWorkerAttendanceAction,
        private readonly UpdateWorkerAttendanceAction $updateWorkerAttendanceAction,
        private readonly DeleteWorkerAttendanceAction $deleteWorkerAttendanceAction,
    ) {
    }

    public function create(DailyLog $dailyLog, CreateWorkerAttendanceData $data, Worker $currentWorker,): WorkerAttendance {
        return $this->createWorkerAttendanceAction->execute($dailyLog, $data, $currentWorker,);
    }

    public function findById(int $id,){
        return WorkerAttendance::find($id);
    }

    public function get(DailyLog $dailyLog, GetWorkerAttendancesData $data,): Collection {
        return (new WorkerAttendanceFilter($data))
            ->apply($this->query($dailyLog))
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }

    public function update(DailyLog $dailyLog, WorkerAttendance $attendance, UpdateWorkerAttendanceData $data, Worker $worker,): WorkerAttendance
    {
        return $this->updateWorkerAttendanceAction->execute($dailyLog, $attendance, $data, $worker,);
    }

    public function delete(DailyLog $dailyLog, WorkerAttendance $workerAttendance, Worker $currentWorker, string $reason): void {
        $this->deleteWorkerAttendanceAction->execute($dailyLog, $workerAttendance, $currentWorker, $reason);
    }
}
