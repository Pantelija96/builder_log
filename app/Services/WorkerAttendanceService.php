<?php

namespace App\Services;

use App\Actions\WorkerAttendance\CreateWorkerAttendanceAction;
use App\Actions\WorkerAttendance\DeleteWorkerAttendanceAction;
use App\Actions\WorkerAttendance\UpdateWorkerAttendanceAction;
use App\DTO\WorkerAttendance\CreateWorkerAttendanceData;
use App\DTO\WorkerAttendance\GetWorkerAttendancesData;
use App\DTO\WorkerAttendance\UpdateWorkerAttendanceData;
use App\Enums\WorkerRole;
use App\Models\DailyLog;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\QueryFilters\AttendancePeriod;
use App\QueryFilters\WorkerAttendanceFilter;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkerAttendanceService
{
    private function query(DailyLog $dailyLog)
    {
        return WorkerAttendance::query()
            ->whereBelongsTo($dailyLog)
            ->where('company_id', $dailyLog->company_id)
            ->with([
                'worker',
                'creator',
                'machineAssignment.machine',
            ]);
    }

    private function globalQuery(Worker $currentWorker)
    {
        return WorkerAttendance::query()
            ->where('worker_attendances.company_id', $currentWorker->company_id)
            ->when($currentWorker->isSiteManager(), fn (Builder $query) => $query->whereIn(
                'worker_attendances.construction_site_id',
                $this->assignedSites($currentWorker)->select('construction_sites.id')->getQuery(),
            ))
            ->with([
                'worker',
                'creator',
                'constructionSite',
                'siteManager',
                'machineAssignment.machine',
            ]);
    }

    private function assignedSites(Worker $worker): BelongsToMany
    {
        return $worker->constructionSites()->where('construction_sites.company_id', $worker->company_id);
    }

    public function __construct(
        private readonly CreateWorkerAttendanceAction $createWorkerAttendanceAction,
        private readonly UpdateWorkerAttendanceAction $updateWorkerAttendanceAction,
        private readonly DeleteWorkerAttendanceAction $deleteWorkerAttendanceAction,
    ) {}

    public function create(DailyLog $dailyLog, CreateWorkerAttendanceData $data, Worker $currentWorker): WorkerAttendance
    {
        return $this->createWorkerAttendanceAction->execute($dailyLog, $data, $currentWorker)
            ->load(['worker', 'creator']);
    }

    public function findById(int $id)
    {
        return WorkerAttendance::find($id);
    }

    public function get(DailyLog $dailyLog, GetWorkerAttendancesData $data, Worker $worker): Collection
    {
        abort_unless($dailyLog->company_id === $worker->company_id, 404);
        abort_if($worker->isSiteManager() && ! $this->assignedSites($worker)->whereKey($dailyLog->construction_site_id)->exists(), 403);

        return (new WorkerAttendanceFilter($data))
            ->apply($this->query($dailyLog))
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }

    public function getAll(Worker $currentWorker, GetWorkerAttendancesData $data, ?CarbonInterface $at = null): Collection
    {
        abort_if($at !== null && ! $currentWorker->isAdmin(), 403);
        $query = (new WorkerAttendanceFilter($data))->apply($this->globalQuery($currentWorker));

        if ($at !== null) {
            AttendancePeriod::attendances($query, $at, null);
        }

        return $query
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }

    public function getForBusinessDate(Worker $currentWorker, string $date, ?int $constructionSiteId, ?int $siteManagerId): Collection
    {
        return $this->globalQuery($currentWorker)
            ->withOnly(['worker', 'creator', 'constructionSite'])
            ->whereDate('worker_attendances.date', $date)
            ->when($constructionSiteId !== null, fn ($query) => $query->where('construction_site_id', $constructionSiteId))
            ->when($siteManagerId !== null, fn ($query) => $query->where('site_manager_id', $siteManagerId))
            ->orderBy('started_at')
            ->orderBy('id')
            ->get();
    }

    public function getAttendanceRoster(Worker $currentWorker): Collection
    {
        return Worker::query()
            ->where('company_id', $currentWorker->company_id)
            ->whereIn('role', [WorkerRole::WORKER, WorkerRole::OPERATOR, WorkerRole::DRIVER])
            ->inDisplayOrder()
            ->get();
    }

    public function update(DailyLog $dailyLog, WorkerAttendance $attendance, UpdateWorkerAttendanceData $data, Worker $worker): WorkerAttendance
    {
        return $this->updateWorkerAttendanceAction->execute($dailyLog, $attendance, $data, $worker);
    }

    public function delete(DailyLog $dailyLog, WorkerAttendance $workerAttendance, Worker $currentWorker, string $reason): void
    {
        $this->deleteWorkerAttendanceAction->execute($dailyLog, $workerAttendance, $currentWorker, $reason);
    }

    private function absentWorkerQuery(Worker $currentWorker): Builder
    {
        return Worker::query()
            ->where('company_id', $currentWorker->company_id)
            ->where('is_active', true)
            ->whereIn('role', [
                'worker',
                'operator',
                'driver',
            ])
            ->orderBy('first_name')
            ->orderBy('last_name');
    }

    public function getLiveAbsentWorkers(Worker $currentWorker, CarbonInterface $clock): Collection
    {
        $companyId = $currentWorker->company_id;
        $dayStart = $clock->avoidMutation()->startOfDay();

        return $this->absentWorkerQuery($currentWorker)
            ->whereDoesntHave('attendances', fn (Builder $query) => AttendancePeriod::attendances(
                $query->where('worker_attendances.company_id', $companyId),
                $clock,
                null,
            ))
            ->with(['attendances' => fn (HasMany $relation) => AttendancePeriod::attendances(
                $relation->getQuery()->where('worker_attendances.company_id', $companyId)
                    ->where('finished_at', '<=', $clock),
                $dayStart,
                $clock->avoidMutation()->addSecond(),
            )->with(['constructionSite', 'creator'])->orderBy('started_at')->orderBy('id')])
            ->get();
    }

    public function getAbsentWorkers(Worker $currentWorker, ?string $date = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        if ($date !== null) {
            $dateFrom = $date;
            $dateTo = $date;
        }

        $clock = AttendancePeriod::local(now());
        $from = Carbon::parse($dateFrom, $clock->getTimezone())->startOfDay();
        $to = Carbon::parse($dateTo, $clock->getTimezone())->startOfDay();
        $workers = $this->absentWorkerQuery($currentWorker)->get();

        $today = $clock->toDateString();
        $firstDate = $from->toDateString();
        $lastDate = $to->toDateString();
        $query = WorkerAttendance::query()->where('company_id', $currentWorker->company_id);

        $presentToday = $today >= $firstDate && $today <= $lastDate
            ? AttendancePeriod::attendances(clone $query, $clock, null)->distinct()->pluck('worker_id')->all()
            : [];

        $presentByDate = $firstDate === $today && $lastDate === $today
            ? collect()
            : $query->whereDate('date', '>=', $firstDate)
                ->whereDate('date', '<=', $lastDate)
                ->whereDate('date', '<>', $today)
                ->get(['worker_id', 'date'])
                ->groupBy(fn (WorkerAttendance $attendance) => $attendance->date->toDateString())
                ->map(fn ($attendances) => $attendances->pluck('worker_id')->unique()->all());

        /*
        |--------------------------------------------------------------------------
        | Build absent workers for each date
        |--------------------------------------------------------------------------
        */

        $result = [];

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $dateKey = $day->toDateString();
            $presentWorkerIds = $dateKey === $today ? $presentToday : $presentByDate->get($dateKey, []);

            $result[] = [
                'date' => $dateKey,
                'absent_workers' => $workers->whereNotIn('id', $presentWorkerIds)->values(),
            ];
        }

        return $result;
    }
}
