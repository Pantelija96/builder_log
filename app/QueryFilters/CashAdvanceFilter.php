<?php

namespace App\QueryFilters;

use App\DTO\CashAdvance\GetCashAdvancesData;
use Illuminate\Database\Eloquent\Builder;

class CashAdvanceFilter extends BaseFilter
{
    protected array $sortable = [
        'id',
        'amount',
        'date',
        'created_at',
    ];

    protected string $defaultSort = 'date';

    public function __construct(
        private readonly GetCashAdvancesData $data,
    ) {
    }

    public function apply(Builder $query): Builder
    {
        return $query

            ->when(
                $this->data->list->search,
                function (Builder $query, string $search) {

                    $query->whereHas(
                        'siteManager',
                        fn (Builder $query) => $query
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                    );

                }
            )

            ->when(
                $this->data->siteManagerId,
                fn (Builder $query, int $id) =>
                $query->where('site_manager_id', $id)
            )

            ->when(
                $this->data->dateFrom,
                fn (Builder $query, $date) =>
                $query->whereDate('date', '>=', $date)
            )

            ->when(
                $this->data->dateTo,
                fn (Builder $query, $date) =>
                $query->whereDate('date', '<=', $date)
            )

            ->when(
                $this->data->minAmount,
                fn (Builder $query, float $amount) =>
                $query->where('amount', '>=', $amount)
            )

            ->when(
                $this->data->maxAmount,
                fn (Builder $query, float $amount) =>
                $query->where('amount', '<=', $amount)
            )

            ->orderBy(
                $this->resolveSort($this->data->list->sort),
                $this->resolveDirection($this->data->list->direction),
            );
    }
}
