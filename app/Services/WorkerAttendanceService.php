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
use Carbon\Carbon;
use Carbon\CarbonPeriod;
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
                'machineAssignment.machine',
            ]);
    }

    private function globalQuery(Worker $currentWorker)
    {
        return WorkerAttendance::query()
            ->where('company_id', $currentWorker->company_id)
            ->with([
                'worker',
                'creator',
                'constructionSite',
                'siteManager',
                'machineAssignment.machine',
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

    public function getAll(Worker $currentWorker, GetWorkerAttendancesData $data,): Collection {
        return (new WorkerAttendanceFilter($data))
            ->apply($this->globalQuery($currentWorker))
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

    public function getAbsentWorkers(Worker $currentWorker, ?string $date = null, ?string $dateFrom = null, ?string $dateTo = null,): array {
        if ($date !== null) {
            $dateFrom = $date;
            $dateTo = $date;
        }

        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->startOfDay();

        /*
        |--------------------------------------------------------------------------
        | Workers that participate in attendance system
        |--------------------------------------------------------------------------
        */

        $workers = Worker::query()
            ->where('company_id', $currentWorker->company_id)
            ->where('is_active', true)
            ->whereIn('role', [
                'worker',
                'operator',
                'driver',
            ])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Attendances for whole requested period
        |--------------------------------------------------------------------------
        |
        | One query for the whole period.
        | We do NOT query the database separately for every day.
        |
        */

        $attendances = WorkerAttendance::query()
            ->where('company_id', $currentWorker->company_id)
            ->whereBetween('date', [
                $from->toDateString(),
                $to->toDateString(),
            ])
            ->get([
                'worker_id',
                'date',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Present workers grouped by date
        |--------------------------------------------------------------------------
        */

        $presentByDate = $attendances
            ->groupBy(
                fn (WorkerAttendance $attendance) =>
                $attendance->date->toDateString()
            )
            ->map(
                fn ($items) =>
                $items
                    ->pluck('worker_id')
                    ->unique()
                    ->values()
            );

        /*
        |--------------------------------------------------------------------------
        | Build absent workers for each date
        |--------------------------------------------------------------------------
        */

        $result = [];

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $dateKey = $day->toDateString();

            $presentWorkerIds = $presentByDate
                ->get($dateKey, collect());

            $absentWorkers = $workers
                ->whereNotIn('id', $presentWorkerIds)
                ->values();

            $result[] = [
                'date' => $dateKey,
                'absent_workers' => $absentWorkers,
            ];
        }

        return $result;
    }
}
