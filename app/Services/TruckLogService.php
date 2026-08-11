<?php

namespace App\Services;

use App\Actions\TruckLog\CreateTruckLogAction;
use App\Actions\TruckLog\DeleteTruckLogAction;
use App\Actions\TruckLog\UpdateTruckLogAction;
use App\DTO\TruckLog\CreateTruckLogData;
use App\DTO\TruckLog\GetTruckLogsData;
use App\DTO\TruckLog\UpdateTruckLogData;
use App\Models\TruckLog;
use App\Models\Worker;
use App\QueryFilters\TruckLogFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TruckLogService
{
    public function __construct(
        private readonly CreateTruckLogAction $createTruckLogAction,
        private readonly UpdateTruckLogAction $updateTruckLogAction,
        private readonly DeleteTruckLogAction $deleteTruckLogAction,
    ) {
    }

    public function get(Worker $currentWorker, GetTruckLogsData $data,): Collection
    {
        $query = TruckLog::query()
            ->where(
                'company_id',
                $currentWorker->company_id,
            )
            ->with([
                'machine',
                'worker',
                'creator',
            ]);

        $this->applyWorkerScope(query: $query, currentWorker: $currentWorker,);

        return (new TruckLogFilter($data))
            ->apply($query)
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }

    public function findById(Worker $currentWorker, int $id,): ?TruckLog
    {
        $query = TruckLog::query()
            ->where(
                'company_id',
                $currentWorker->company_id,
            )
            ->with([
                'machine',
                'worker',
                'creator',
            ]);

        $this->applyWorkerScope(query: $query, currentWorker: $currentWorker,);

        return $query
            ->whereKey($id)
            ->first();
    }

    public function create(CreateTruckLogData $data, Worker $currentWorker,): TruckLog
    {
        /*
         * Operator creates a session for himself.
         */
        if ($currentWorker->isDriver()) {
            return $this->createTruckLogAction->execute(data: $data, currentWorker: $currentWorker, workerId: $currentWorker->id,);
        }

        /*
         * Admin / Site Manager create a session
         */
        if ($currentWorker->isAdmin() || $currentWorker->isSiteManager())
        {
            if (! $data->workerId) {
                abort(422, 'Worker is required.');
            }
            return $this->createTruckLogAction->execute(data: $data, currentWorker: $currentWorker, workerId: $data->workerId,);
        }

        abort(403);
    }

    public function update(TruckLog $truckLog, UpdateTruckLogData $data, Worker $currentWorker, ?string $reason = null,): TruckLog
    {
        $this->ensureCompanyAccess(truckLog: $truckLog, currentWorker: $currentWorker,);
        $this->ensureCanUpdate(truckLog: $truckLog, currentWorker: $currentWorker,);
        return $this->updateTruckLogAction->execute(truckLog: $truckLog, data: $data, currentWorker: $currentWorker, reason: $reason,);
    }

    public function delete(TruckLog $truckLog, Worker $currentWorker, string $reason,): void
    {
        $this->ensureCompanyAccess(truckLog: $truckLog, currentWorker: $currentWorker,);
        $this->ensureCanUpdate(truckLog: $truckLog, currentWorker: $currentWorker,);
        $this->deleteTruckLogAction->execute(truckLog: $truckLog, currentWorker: $currentWorker, reason: $reason,);
    }

    private function applyWorkerScope(Builder $query, Worker $currentWorker,): void
    {
        if ($currentWorker->isAdmin()) {
            return;
        }

        if ($currentWorker->isDriver()) {
            $query->where(
                'worker_id',
                $currentWorker->id,
            );

            return;
        }
    }

    private function ensureCanUpdate(TruckLog $truckLog, Worker $currentWorker,): void
    {
        if ($currentWorker->isAdmin())
        {
            return;
        }

        if ($currentWorker->isDriver()) {

            if ($truckLog->worker_id !== $currentWorker->id)
            {
                abort(403);
            }

            return;
        }

        if ($currentWorker->isSiteManager())
        {
            return;
        }

        abort(403);
    }

    private function ensureCompanyAccess(TruckLog $truckLog, Worker $currentWorker,): void
    {
        if ($truckLog->company_id !== $currentWorker->company_id)
        {
            abort(404);
        }
    }
}
