<?php

namespace App\Services;

use App\DTO\Requests\GetSuppliersData;
use App\Models\Supplier;
use App\QueryFilters\SupplierFilter;
use Illuminate\Database\Eloquent\Collection;

class SupplierService
{
    public function getAll(GetSuppliersData $data): Collection
    {
        $query = Supplier::query()
            ->with('company');

        $query = (new SupplierFilter($data))
            ->apply($query);

        return $query
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }
}
