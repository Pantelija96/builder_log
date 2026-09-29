<?php

namespace App\QueryFilters;

use App\DTO\WorkerPayment\GetWorkerPaymentsData;
use Illuminate\Database\Eloquent\Builder;

class WorkerPaymentFilter
{
    public function __construct(
        private readonly GetWorkerPaymentsData $data,
    ) {
    }

    public function apply(Builder $query): Builder
    {
        return $query
            ->when(
                $this->data->workerId,
                fn (Builder $query, int $workerId) =>
                $query->where('worker_id', $workerId)
            )
            ->when(
                $this->data->dateFrom,
                fn (Builder $query) =>
                $query->whereDate(
                    'date',
                    '>=',
                    $this->data->dateFrom->toDateString()
                )
            )
            ->when(
                $this->data->dateTo,
                fn (Builder $query) =>
                $query->whereDate(
                    'date',
                    '<=',
                    $this->data->dateTo->toDateString()
                )
            );
    }
}
