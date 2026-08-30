<?php

namespace App\Services;

use App\Actions\SubcontractorLog\CreateSubcontractorLogAction;
use App\Actions\SubcontractorLog\DeleteSubcontractorLogAction;
use App\Actions\SubcontractorLog\UpdateSubcontractorLogAction;
use App\DTO\SubcontractorLog\CreateSubcontractorLogData;
use App\DTO\SubcontractorLog\GetSubcontractorLogsData;
use App\DTO\SubcontractorLog\UpdateSubcontractorLogData;
use App\Models\DailyLog;
use App\Models\SubcontractorLog;
use App\Models\Worker;
use App\QueryFilters\SubcontractorLogFilter;
use Illuminate\Database\Eloquent\Collection;

class SubcontractorLogService
{
    public function __construct(
        private readonly CreateSubcontractorLogAction $createAction,
        private readonly UpdateSubcontractorLogAction $updateAction,
        private readonly DeleteSubcontractorLogAction $deleteAction,
    ) {
    }

    private function query(DailyLog $dailyLog)
    {
        return SubcontractorLog::query()
            ->whereBelongsTo($dailyLog)
            ->with([
                'subcontractor',
                'creator',
                'siteManager',
                'dailyLog'
            ]);
    }

    public function get(
        Worker $currentWorker,
        GetSubcontractorLogsData $data,
    ): Collection {
        $query = SubcontractorLog::query()
            ->where(
                'company_id',
                $currentWorker->company_id,
            )
            ->with([
                'subcontractor',
                'creator',
                'siteManager',
            ]);

        return (new SubcontractorLogFilter($data))
            ->apply($query)
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }

    public function create(DailyLog $dailyLog, CreateSubcontractorLogData $data, Worker $currentWorker,): SubcontractorLog {
        return $this->createAction->execute(
            $dailyLog,
            $data,
            $currentWorker,
        );
    }

    public function update(SubcontractorLog $subcontractorLog, UpdateSubcontractorLogData $data, Worker $worker, string $reason,): SubcontractorLog {
        return $this->updateAction->execute(
            $subcontractorLog,
            $data,
            $worker,
            $reason,
        );
    }

    public function delete(
        SubcontractorLog $subcontractorLog,
        Worker $worker,
        string $reason,
    ): void {

        $this->deleteAction->execute(
            subcontractorLog: $subcontractorLog,
            currentWorker: $worker,
            reason: $reason,
        );
    }
}
