<?php

namespace App\QueryFilters;

use App\DTO\TruckLog\GetTruckLogsData;
use Illuminate\Database\Eloquent\Builder;

class TruckLogFilter extends BaseFilter
{
    protected array $sortable = [
        'id',
        'date',
        'site_manager_started_at',
        'site_manager_finished_at',
        'operator_started_at',
        'operator_finished_at',
        'start_mileage',
        'end_mileage',
        'created_at',
    ];

    protected string $defaultSort = 'date';

    public function __construct(
        private readonly GetTruckLogsData $data,
    ) {
    }

    public function apply(Builder $query): Builder
    {
        return $query
            ->when(
                $this->data->machineId,
                fn (Builder $query, int $machineId) =>
                $query->where('machine_id', $machineId)
            )

            ->when(
                $this->data->workerId,
                fn (Builder $query, int $workerId) =>
                $query->where('worker_id', $workerId)
            )

            ->when(
                $this->data->dateFrom,
                fn (Builder $query, string $date) =>
                $query->whereDate('date', '>=', $date)
            )

            ->when(
                $this->data->dateTo,
                fn (Builder $query, string $date) =>
                $query->whereDate('date', '<=', $date)
            )

            ->orderBy(
                $this->resolveSort($this->data->list->sort),
                $this->resolveDirection($this->data->list->direction),
            );
    }
}
