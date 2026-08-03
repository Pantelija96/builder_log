<?php

namespace App\QueryFilters;

use App\DTO\Requests\GetSuppliersData;
use Illuminate\Database\Eloquent\Builder;

class SupplierFilter extends BaseFilter
{
    public const SORTABLE = [
        'id',
        'name',
        'pib',
        'created_at',
    ];

    protected array $sortable = self::SORTABLE;

    public function __construct(
        protected readonly GetSuppliersData $data,
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
                            ->orWhere('pib', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
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
                !is_null($this->data->isActive),
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
