<?php

namespace App\Services;

use App\Actions\Worker\CreateWorkerAction;
use App\Actions\Worker\DeleteWorkerAction;
use App\Actions\Worker\GetWorkerWorkHistoryAction;
use App\Actions\Worker\UpdateWorkerAction;
use App\DTO\Requests\GetWorkersData;
use App\DTO\Worker\CreateWorkerData;
use App\DTO\Worker\GetWorkerWorkHistoryData;
use App\DTO\Worker\UpdateWorkerData;
use App\Models\Worker;
use App\QueryFilters\WorkerFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class WorkerService
{
    public function __construct(
        private readonly CreateWorkerAction $createWorkerAction,
        private readonly UpdateWorkerAction $updateWorkerAction,
        private readonly DeleteWorkerAction $deleteWorkerAction,
        private readonly GetWorkerWorkHistoryAction $getWorkerWorkHistoryAction,
    ) {}

    private function query(Worker $actor): Builder
    {
        return Worker::query()->with([
            'company',
            'constructionSites' => fn ($sites) => $sites->where('construction_sites.company_id', $actor->company_id),
        ]);
    }

    public function getAll(GetWorkersData $data, Worker $actor): Collection
    {
        $now = now();
        $query = $this->query($actor)
            ->where('company_id', $actor->company_id)
            ->withExists([
                'machineAssignments as has_occupied_machine' => fn (Builder $assignments) => $assignments
                    ->whereColumn('machine_assignments.company_id', 'workers.company_id')
                    ->whereDate('machine_assignments.date', $now->toDateString())
                    ->occupiedAt($now),
                'attendances as has_current_attendance' => fn (Builder $attendances) => $attendances
                    ->whereColumn('worker_attendances.company_id', 'workers.company_id')
                    ->whereDate('worker_attendances.date', $now->toDateString())
                    ->where('started_at', '<=', $now)
                    ->where(fn (Builder $period) => $period
                        ->whereNull('finished_at')
                        ->orWhere('finished_at', '>', $now)),
            ]);

        return (new WorkerFilter($data))
            ->apply($query)
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }

    public function findById(int $id, Worker $actor): Worker
    {
        return $this->query($actor)->where('company_id', $actor->company_id)->findOrFail($id);
    }

    public function create(CreateWorkerData $data, Worker $currentWorker): Worker
    {
        $this->ensureAdmin($currentWorker);

        return $this->createWorkerAction->execute(
            data: $data,
            companyId: $currentWorker->company_id,
        );
    }

    public function update(Worker $worker, UpdateWorkerData $data, Worker $currentWorker, ?string $reason = null): Worker
    {
        $this->ensureAdmin($currentWorker);
        $this->ensureCompanyAccess($worker, $currentWorker);

        return $this->updateWorkerAction->execute(
            worker: $worker,
            data: $data,
            currentWorker: $currentWorker,
            reason: $reason,
        );
    }

    public function delete(Worker $worker, Worker $currentWorker, string $reason): void
    {
        $this->ensureAdmin($currentWorker);
        $this->ensureCompanyAccess($worker, $currentWorker);

        $this->deleteWorkerAction->execute(
            worker: $worker,
            currentWorker: $currentWorker,
            reason: $reason,
        );
    }

    public function getWorkHistory(Worker $worker, GetWorkerWorkHistoryData $data, Worker $currentWorker)
    {
        return $this->getWorkerWorkHistoryAction->execute(
            worker: $worker,
            data: $data,
            currentWorker: $currentWorker,
        );
    }

    private function ensureAdmin(Worker $worker): void
    {
        if (! $worker->isAdmin()) {
            abort(403);
        }
    }

    private function ensureCompanyAccess(Worker $worker, Worker $currentWorker): void
    {
        if ($worker->company_id !== $currentWorker->company_id) {
            abort(404);
        }
    }
}
