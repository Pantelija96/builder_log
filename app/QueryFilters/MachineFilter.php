<?php

namespace App\QueryFilters;

use App\DTO\Requests\GetMachinesData;
use Illuminate\Database\Eloquent\Builder;

class MachineFilter extends BaseFilter
{
    public const SORTABLE = [
        'id',
        'name',
        'type',
        'status',
        'created_at',
    ];

    protected array $sortable = self::SORTABLE;

    public function __construct(
        protected readonly GetMachinesData $data,
    ) {
    }

    public function apply(Builder $query): Builder
    {
        $search = $this->data->list->search;

        return $query
            ->when($search, function (Builder $query) use ($search) {
                $query->where(
                    'name',
                    'like',
                    "%{$search}%"
                );
            })

            ->when(
                $this->data->companyId,
                fn (Builder $query) => $query->where(
                    'company_id',
                    $this->data->companyId
                )
            )

            ->when(
                $this->data->type,
                fn (Builder $query) => $query->where(
                    'type',
                    $this->data->type->value
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
                $this->data->status,
                fn (Builder $query) => $query->where(
                    'status',
                    $this->data->status->value
                )
            )

            ->when(
                $this->data->ownerType,
                fn (Builder $query) => $query->where(
                    'owner_type',
                    $this->data->ownerType->value
                )
            )

            ->when(
                $this->data->ownerId,
                fn (Builder $query) => $query->where(
                    'owner_id',
                    $this->data->ownerId
                )
            )

            ->when(
                $this->data->exclude_type,
                fn (Builder $query) => $query->where(
                    'type',
                    '!=',
                    $this->data->exclude_type->value
                )
            )

            ->orderBy(
                $this->resolveSort($this->data->list->sort),
                $this->resolveDirection($this->data->list->direction),
            );
    }
}
