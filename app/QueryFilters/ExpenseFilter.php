<?php

namespace App\QueryFilters;

use App\DTO\Expense\GetExpensesData;
use Illuminate\Database\Eloquent\Builder;

class ExpenseFilter extends BaseFilter
{
    protected array $sortable = [
        'id',
        'title',
        'amount',
        'date',
        'created_at',
    ];

    protected string $defaultSort = 'date';

    public function __construct(
        private readonly GetExpensesData $data,
    ) {
    }

    public function apply(Builder $query): Builder
    {
        return $query
            ->when(
                $this->data->search,
                fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search) {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                })
            )

            ->when(
                $this->data->title,
                fn (Builder $query, string $title) => $query->where('title', 'like', "%{$title}%")
            )

            ->when(
                $this->data->createdBy,
                fn (Builder $query, int $createdBy) => $query->where('created_by', $createdBy)
            )

            ->when(
                $this->data->dateFrom,
                fn (Builder $query, $date) => $query->whereDate('date', '>=', $date)
            )

            ->when(
                $this->data->dateTo,
                fn (Builder $query, $date) => $query->whereDate('date', '<=', $date)
            )

            ->when(
                $this->data->minAmount,
                fn (Builder $query, float $amount) => $query->where('amount', '>=', $amount)
            )

            ->when(
                $this->data->maxAmount,
                fn (Builder $query, float $amount) => $query->where('amount', '<=', $amount)
            )

            ->when(
                $this->data->constructionSiteId,
                fn (Builder $query, int $id) => $query->where('construction_site_id', $id)
            )

            ->when(
                $this->data->siteManagerId,
                fn (Builder $query, int $id) => $query->where('site_manager_id', $id)
            )

            ->orderBy(
                $this->resolveSort($this->data->list->sort),
                $this->resolveDirection($this->data->list->direction),
            );
    }
}
