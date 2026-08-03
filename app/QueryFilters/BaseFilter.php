<?php

namespace App\QueryFilters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

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
        return $direction === 'desc'
            ? 'desc'
            : 'asc';
    }

    abstract public function apply(Builder $query): Builder;
}
