<?php

namespace App\QueryFilters;

use App\DTO\Requests\GetCompaniesData;
use Illuminate\Database\Eloquent\Builder;

class CompanyFilter extends BaseFilter
{
    public const SORTABLE = [
        'id',
        'name',
        'created_at',
    ];

    protected array $sortable = self::SORTABLE;

    public function __construct(
        protected readonly GetCompaniesData $data,
    ) {
    }

    public function apply(Builder $query): Builder
    {
        $search = $this->data->list->search;

        $sort = $this->resolveSort($this->data->list->sort);

        return $query
            ->when(
                $search,
                function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%");
                }
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
                $this->data->pib,
                fn (Builder $query) => $query->where(
                    'pib',
                    $this->data->pib
                )
            )

            ->when(
                $this->data->email,
                fn (Builder $query) => $query->where(
                    'email',
                    'like',
                    "%{$this->data->email}%"
                )
            )

            ->when(
                $this->data->phone,
                fn (Builder $query) => $query->where(
                    'phone',
                    'like',
                    "%{$this->data->phone}%"
                )
            )

            ->when(
                $this->data->address,
                fn (Builder $query) => $query->where(
                    'address',
                    'like',
                    "%{$this->data->address}%"
                )
            )
            ->orderBy(
                $sort,
                $this->resolveDirection($this->data->list->direction)
            );
    }
}
