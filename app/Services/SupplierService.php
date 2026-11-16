<?php

namespace App\Services;

use App\Actions\Supplier\CreateSupplierAction;
use App\Actions\Supplier\DeleteSupplierAction;
use App\Actions\Supplier\UpdateSupplierAction;
use App\DTO\Requests\GetSuppliersData;
use App\DTO\Supplier\CreateSupplierData;
use App\DTO\Supplier\UpdateSupplierData;
use App\Models\Supplier;
use App\Models\Worker;
use App\QueryFilters\SupplierFilter;
use Illuminate\Database\Eloquent\Collection;

class SupplierService
{
    public function __construct(
        private readonly CreateSupplierAction $createSupplierAction,
        private readonly UpdateSupplierAction $updateSupplierAction,
        private readonly DeleteSupplierAction $deleteSupplierAction,
    ) {
    }

    public function get(
        Worker $currentWorker,
        GetSuppliersData $data,
    ): Collection {
        $query = Supplier::query()
            ->where(
                'company_id',
                $currentWorker->company_id
            )
            ->with('company');

        return (new SupplierFilter($data))
            ->apply($query)
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }

    public function findById(
        Worker $currentWorker,
        int $id,
    ): ?Supplier {
        return Supplier::query()
            ->where(
                'company_id',
                $currentWorker->company_id
            )
            ->with('company')
            ->whereKey($id)
            ->first();
    }

    public function create(
        CreateSupplierData $data,
        Worker $currentWorker,
    ): Supplier {
        if (! $currentWorker->isAdmin()) {
            abort(403);
        }

        return $this->createSupplierAction->execute(
            data: $data,
            currentWorker: $currentWorker,
        );
    }

    public function update(
        Supplier $supplier,
        UpdateSupplierData $data,
        Worker $currentWorker,
    ): Supplier {
        if (! $currentWorker->isAdmin()) {
            abort(403);
        }

        $this->ensureCompanyAccess(
            supplier: $supplier,
            currentWorker: $currentWorker,
        );

        return $this->updateSupplierAction->execute(
            supplier: $supplier,
            data: $data,
            currentWorker: $currentWorker,
        );
    }

    public function delete(
        Supplier $supplier,
        Worker $currentWorker,
        string $reason,
    ): void {
        if (! $currentWorker->isAdmin()) {
            abort(403);
        }

        $this->ensureCompanyAccess(
            supplier: $supplier,
            currentWorker: $currentWorker,
        );

        $this->deleteSupplierAction->execute(
            supplier: $supplier,
            currentWorker: $currentWorker,
            reason: $reason,
        );
    }

    private function ensureCompanyAccess(
        Supplier $supplier,
        Worker $currentWorker,
    ): void {
        if (
            $supplier->company_id !==
            $currentWorker->company_id
        ) {
            abort(404);
        }
    }
}
