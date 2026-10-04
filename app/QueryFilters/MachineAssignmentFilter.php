<?php

namespace App\QueryFilters;

use App\DTO\MachineAssignment\GetMachineAssignmentsData;
use Illuminate\Database\Eloquent\Builder;

class MachineAssignmentFilter extends BaseFilter
{
    public const SORTABLE = [
        'id',
        'date',
        'created_at',
        'updated_at',
    ];

    protected array $sortable = self::SORTABLE;

    public function __construct(
        protected readonly GetMachineAssignmentsData $data,
    ) {
    }

    public function apply(Builder $query): Builder
    {
        return $query
            ->when(
                $this->data->date,
                fn (Builder $query) => $query->whereDate(
                    'date',
                    $this->data->date,
                )
            )

            ->when(
                $this->data->dateFrom,
                fn (Builder $query) => $query->whereDate(
                    'date',
                    '>=',
                    $this->data->dateFrom,
                )
            )

            ->when(
                $this->data->dateTo,
                fn (Builder $query) => $query->whereDate(
                    'date',
                    '<=',
                    $this->data->dateTo,
                )
            )

            ->when(
                $this->data->machineId,
                fn (Builder $query) => $query->where(
                    'machine_id',
                    $this->data->machineId,
                )
            )

            ->when(
                $this->data->machineType,
                fn (Builder $query) => $query->whereHas(
                    'machine',
                    fn (Builder $machineQuery) => $machineQuery->where(
                        'type',
                        $this->data->machineType,
                    )
                )
            )

            ->when(
                $this->data->workerId,
                fn (Builder $query) => $query->where(
                    'worker_id',
                    $this->data->workerId,
                )
            )

            ->when(
                $this->data->constructionSiteId,
                fn (Builder $query) => $query->where(
                    'construction_site_id',
                    $this->data->constructionSiteId,
                )
            )

            ->when(
                $this->data->siteManagerId,
                fn (Builder $query) => $query->where(
                    'site_manager_id',
                    $this->data->siteManagerId,
                )
            )

            ->when(
                $this->data->createdBy,
                fn (Builder $query) => $query->where(
                    'created_by',
                    $this->data->createdBy,
                )
            )

            ->when(
                $this->data->deleted,
                fn (Builder $query) => $query->withTrashed()
            )

            ->orderBy(
                $this->resolveSort(
                    $this->data->list->sort
                ),
                $this->resolveDirection(
                    $this->data->list->direction
                ),
            );
    }
}
