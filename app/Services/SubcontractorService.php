<?php

namespace App\Services;

use App\DTO\Requests\GetSubcontractorsData;
use App\Models\Subcontractor;
use App\QueryFilters\SubcontractorFilter;
use Illuminate\Database\Eloquent\Collection;

class SubcontractorService
{
    public function getAll(GetSubcontractorsData $data): Collection
    {
        $query = Subcontractor::query()
            ->with('company');

        if ($data->dailyLogId) {
            $query->with([
                'logs' => fn ($query) => $query->where('daily_log_id', $data->dailyLogId),
            ]);
        }

        $query = (new SubcontractorFilter($data))
            ->apply($query);

        return $query
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }
}
