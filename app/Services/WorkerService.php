<?php

namespace App\Services;

use App\DTO\Requests\GetWorkersData;
use App\Models\Worker;
use App\QueryFilters\WorkerFilter;
use Illuminate\Database\Eloquent\Collection;

class WorkerService
{
    public function getAll(GetWorkersData $data): Collection
    {
        $query = Worker::query()
            ->with('company');

        $query = (new WorkerFilter($data))->apply($query);

        return $query
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }
}
