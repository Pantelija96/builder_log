<?php

namespace App\QueryFilters;

use App\DTO\Requests\GetConstructionSitesData;
use Illuminate\Database\Eloquent\Builder;

class ConstructionSiteFilter extends BaseFilter
{
    public const SORTABLE = [
        'id',
        'name',
        'status',
        'created_at',
    ];

    protected array $sortable = self::SORTABLE;

    public function __construct(
        protected readonly GetConstructionSitesData $data,
    ) {
    }

    public function apply(Builder $query): Builder
    {
        $search = $this->data->list->search;

        $sort = $this->resolveSort(
            $this->data->list->sort
        );

        return $query
            ->when(
                $search,
                function (Builder $query) use ($search) {
                    $query->where(function (Builder $query) use ($search) {
                        $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('address', 'like', "%{$search}%");
                    });
                }
            )
            ->when(
                $this->data->companyId,
                fn (Builder $query) => $query->where(
                    'company_id',
                    $this->data->companyId
                )
            )
            ->when(
                $this->data->name,
                fn (Builder $query) => $query->where(
                    'name',
                    'like',
                    "%{$this->data->name}%"
                )
            )

            ->when(
                $this->data->address,
                fn (Builder $query) => $query->where(
                    'address',
                    $this->data->address
                )
            )
            ->when(
                $this->data->status,
                fn (Builder $query) => $query->where(
                    'status',
                    $this->data->status->value
                )
            )
            ->orderBy(
                $sort,
                $this->resolveDirection(
                    $this->data->list->direction
                )
            );
    }
}
