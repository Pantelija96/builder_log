<?php

namespace App\Services;

use App\Actions\DailyLog\CreateDailyLogAction;
use App\Actions\DailyLog\LockDailyLogAction;
use App\Actions\DailyLog\UnlockDailyLogAction;
use App\DTO\DailyLog\CreateDailyLogData;
use App\DTO\DailyLog\GetDailyLogsData;
use App\Models\ConstructionSite;
use App\Models\DailyLog;
use App\Models\Worker;
use App\QueryFilters\DailyLogFilter;
use Illuminate\Database\Eloquent\Collection;

class DailyLogService
{
    public function __construct(
        private readonly CreateDailyLogAction $createDailyLogAction,
        private readonly LockDailyLogAction $lockDailyLogAction,
        private readonly UnlockDailyLogAction $unlockDailyLogAction,
    ) {}

    public function create(Worker $worker, CreateDailyLogData $data): DailyLog
    {
        return $this->createDailyLogAction->execute(worker: $worker, data: $data)
            ->load(['siteManagers']);
    }

    public function select(Worker $worker, ConstructionSite $site): DailyLog
    {
        return $this->createDailyLogAction->select($worker, $site)
            ->load(['constructionSite', 'siteManagers']);
    }

    public function get(GetDailyLogsData $data, Worker $worker): Collection
    {
        return (new DailyLogFilter($data))
            ->apply(
                DailyLog::query()->visibleTo($worker)
                    ->with([
                        'company',
                        'constructionSite',
                        'siteManagers',
                        'lockedBy',
                        'attachments',
                    ])
            )
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }

    public function findById(int $id, Worker $worker): DailyLog
    {
        return DailyLog::query()->visibleTo($worker)
            ->with([
                'company',
                'constructionSite',
                'siteManagers',
                'lockedBy',

                'workerAttendances',
                'machineAssignments',
                'expenses',
                'deliveryNotes',
                'notes',
                'subcontractorLogs',
                'attachments',
            ])
            ->findOrFail($id);
    }

    public function lock(DailyLog $dailyLog, Worker $worker): DailyLog
    {
        return $this->lockDailyLogAction
            ->execute($dailyLog, $worker)
            ->load(['siteManagers']);
    }

    public function unlock(DailyLog $dailyLog, Worker $worker, string $reason): DailyLog
    {
        return $this->unlockDailyLogAction->execute(dailyLog: $dailyLog, worker: $worker, reason: $reason)
            ->load(['siteManagers']);
    }
}
