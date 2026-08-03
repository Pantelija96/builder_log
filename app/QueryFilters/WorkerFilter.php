<?php

namespace App\QueryFilters;

use App\DTO\Requests\GetWorkersData;
use Illuminate\Database\Eloquent\Builder;

class WorkerFilter extends BaseFilter
{
    public const SORTABLE = [
        'id',
        'first_name',
        'last_name',
        'username',
        'created_at',
        'is_available',
    ];

    protected array $sortable = self::SORTABLE;

    public function __construct(
        protected readonly GetWorkersData $data,
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
                    $query->where(function (Builder $query) use ($search) {
                        $query
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
                }
            )
            ->when(
                $this->data->firstName,
                fn (Builder $query) => $query->where(
                    'first_name',
                    'like',
                    "%{$this->data->firstName}%"
                )
            )

            ->when(
                $this->data->lastName,
                fn (Builder $query) => $query->where(
                    'last_name',
                    'like',
                    "%{$this->data->lastName}%"
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
                $this->data->companyId,
                fn (Builder $query) => $query->where(
                    'company_id',
                    $this->data->companyId
                )
            )
            ->when(
                $this->data->role,
                fn (Builder $query) => $query->where(
                    'role',
                    $this->data->role->value
                )
            )
            ->when(
                ! is_null($this->data->isActive),
                fn (Builder $query) => $query->where(
                    'is_active',
                    $this->data->isActive
                )
            )
            ->orderBy(
                $sort,
                $this->resolveDirection($this->data->list->direction)
            );
    }
}
