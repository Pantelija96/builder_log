<?php

namespace App\Services;

use App\Actions\Subcontractor\CreateSubcontractorAction;
use App\Actions\Subcontractor\DeleteSubcontractorAction;
use App\Actions\Subcontractor\UpdateSubcontractorAction;
use App\DTO\Requests\GetSubcontractorsData;
use App\DTO\Subcontractor\CreateSubcontractorData;
use App\DTO\Subcontractor\UpdateSubcontractorData;
use App\Models\Subcontractor;
use App\Models\Worker;
use App\QueryFilters\SubcontractorFilter;
use Illuminate\Database\Eloquent\Collection;

class SubcontractorService
{
    public function __construct(
        private readonly CreateSubcontractorAction $createSubcontractorAction,
        private readonly UpdateSubcontractorAction $updateSubcontractorAction,
        private readonly DeleteSubcontractorAction $deleteSubcontractorAction,
    ) {
    }

    public function get(
        Worker $currentWorker,
        GetSubcontractorsData $data,
    ): Collection {
        $query = Subcontractor::query()
            ->where('company_id', $currentWorker->company_id)
            ->with('company');

        if ($data->dailyLogId) {
            $query->with([
                'logs' => fn ($query) => $query->where(
                    'daily_log_id',
                    $data->dailyLogId,
                ),
            ]);
        }

        return (new SubcontractorFilter($data))
            ->apply($query)
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }

    public function findById(
        Worker $currentWorker,
        int $id,
    ): ?Subcontractor {
        return Subcontractor::query()
            ->where('company_id', $currentWorker->company_id)
            ->with('company')
            ->whereKey($id)
            ->first();
    }

    public function create(
        CreateSubcontractorData $data,
        Worker $currentWorker,
    ): Subcontractor {
        if (! $currentWorker->isAdmin()) {
            abort(403);
        }

        return $this->createSubcontractorAction->execute(
            data: $data,
            currentWorker: $currentWorker,
        );
    }

    public function update(
        Subcontractor $subcontractor,
        UpdateSubcontractorData $data,
        Worker $currentWorker,
    ): Subcontractor {
        if (! $currentWorker->isAdmin()) {
            abort(403);
        }

        $this->ensureCompanyAccess(
            subcontractor: $subcontractor,
            currentWorker: $currentWorker,
        );

        return $this->updateSubcontractorAction->execute(
            subcontractor: $subcontractor,
            data: $data,
            currentWorker: $currentWorker,
        );
    }

    public function delete(
        Subcontractor $subcontractor,
        Worker $currentWorker,
        string $reason,
    ): void {
        if (! $currentWorker->isAdmin()) {
            abort(403);
        }

        $this->ensureCompanyAccess(
            subcontractor: $subcontractor,
            currentWorker: $currentWorker,
        );

        $this->deleteSubcontractorAction->execute(
            subcontractor: $subcontractor,
            currentWorker: $currentWorker,
            reason: $reason,
        );
    }

    private function ensureCompanyAccess(
        Subcontractor $subcontractor,
        Worker $currentWorker,
    ): void {
        if ($subcontractor->company_id !== $currentWorker->company_id) {
            abort(404);
        }
    }
}
