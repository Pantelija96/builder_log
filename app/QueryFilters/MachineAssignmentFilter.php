<?php

namespace App\QueryFilters;

use App\DTO\MachineAssignment\GetMachineAssignmentsData;
use Illuminate\Database\Eloquent\Builder;

class MachineAssignmentFilter extends BaseFilter
{
    public const SORTABLE = [
        'id',
        'started_at',
        'finished_at',
        'date',
        'created_at',
    ];

    protected array $sortable = self::SORTABLE;

    public function __construct(protected readonly GetMachineAssignmentsData $data,)
    {}

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
                $this->data->workerId,
                fn (Builder $query) => $query->where('worker_id', $this->data->workerId,)
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
