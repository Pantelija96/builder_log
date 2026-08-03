<?php

namespace App\Services;

use App\Actions\DailyLog\CreateDailyLogAction;
use App\Actions\DailyLog\LockDailyLogAction;
use App\Actions\DailyLog\UnlockDailyLogAction;
use App\DTO\DailyLog\CreateDailyLogData;
use App\DTO\DailyLog\GetDailyLogsData;
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
    ) {
    }

    public function create(Worker $worker, CreateDailyLogData $data): DailyLog {
        return $this->createDailyLogAction->execute(worker: $worker, data: $data);
    }

    public function get(GetDailyLogsData $data,): Collection {
        return (new DailyLogFilter($data))
            ->apply(
                DailyLog::query()
                    ->with([
                        'company',
                        'constructionSite',
                        'siteManager',
                        'lockedBy',
                        'attachments',
                    ])
            )
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }

    public function findById(int $id,): DailyLog {
        return DailyLog::query()
            ->with([
                'company',
                'constructionSite',
                'siteManager',
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

    public function lock(DailyLog $dailyLog, Worker $worker,): DailyLog {
        return $this->lockDailyLogAction
            ->execute($dailyLog, $worker);
    }

    public function unlock(DailyLog $dailyLog, Worker $worker, string $reason): DailyLog {
        return $this->unlockDailyLogAction->execute(dailyLog: $dailyLog, worker: $worker, reason: $reason);
    }
}
