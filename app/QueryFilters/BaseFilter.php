<?php

namespace App\QueryFilters;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

abstract class BaseFilter
{
    protected array $sortable = [
        'id',
    ];

    protected string $defaultSort = 'id';

    protected function resolveSort(?string $sort): string
    {
        return in_array($sort, $this->sortable, true)
            ? $sort
            : $this->defaultSort;
    }

    protected function resolveDirection(?string $direction): string
    {
        return $direction === 'desc' ? 'desc' : 'asc';
    }

    protected function applyCreatedAtFilter(Builder $query, ?Carbon $from, ?Carbon $to,): Builder
    {
        return $query
            ->when(
                $from,
                fn (Builder $query, Carbon $date) =>
                $query->whereDate('created_at', '>=', $date)
            )
            ->when(
                $to,
                fn (Builder $query, Carbon $date) =>
                $query->whereDate('created_at', '<=', $date)
            );
    }

    abstract public function apply(Builder $query): Builder;
}
