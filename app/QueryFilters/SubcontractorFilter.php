<?php

namespace App\QueryFilters;

use App\DTO\Requests\GetSubcontractorsData;
use Illuminate\Database\Eloquent\Builder;

class SubcontractorFilter extends BaseFilter
{
    public const SORTABLE = [
        'id',
        'name',
        'pib',
        'created_at',
    ];

    protected array $sortable = self::SORTABLE;

    public function __construct(
        protected readonly GetSubcontractorsData $data,
    ) {
    }

    public function apply(Builder $query): Builder
    {
        $search = $this->data->list->search;

        return $query
            ->when(
                $search,
                function (Builder $query) use ($search) {
                    $query->where(function (Builder $query) use ($search) {
                        $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('pib', 'like', "%{$search}%");
                    });
                }
            )

            ->when(
                ! is_null($this->data->isActive),
                fn (Builder $query) => $query->where(
                    'is_active',
                    $this->data->isActive
                )
            )

            ->orderBy(
                $this->resolveSort($this->data->list->sort),
                $this->resolveDirection($this->data->list->direction),
            );
    }
}
