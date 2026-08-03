<?php

namespace App\Services;

use App\DTO\Requests\GetCompaniesData;
use App\Models\Company;
use App\QueryFilters\CompanyFilter;
use Illuminate\Database\Eloquent\Collection;

class CompanyService
{
    public function getAll(GetCompaniesData $data): Collection
    {
        $query = Company::query();

        $query = (new CompanyFilter($data))->apply($query);

        return $query
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }
}
