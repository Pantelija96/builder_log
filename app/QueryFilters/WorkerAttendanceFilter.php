<?php

namespace App\QueryFilters;

use App\DTO\WorkerAttendance\GetWorkerAttendancesData;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class WorkerAttendanceFilter extends BaseFilter
{
    protected array $sortable = [
        'id',
        'started_at',
        'finished_at',
        'created_at',
    ];

    protected string $defaultSort = 'started_at';

    public function __construct(
        protected readonly GetWorkerAttendancesData $data,
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
                $this->data->list->search,
                function (Builder $query, string $search) {

                    $query->whereHas(
                        'worker',
                        function (Builder $query) use ($search) {

                            $query
                                ->where(
                                    'first_name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'last_name',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )

            ->when(
                $this->data->dateCreatedFrom,
                fn (Builder $query, Carbon $date) =>
                $query->whereDate(
                    'created_at',
                    '>=',
                    $date
                )
            )

            ->when(
                $this->data->dateCreatedTo,
                fn (Builder $query, Carbon $date) =>
                $query->whereDate(
                    'created_at',
                    '<=',
                    $date
                )
            )

            ->orderBy(
                $this->resolveSort(
                    $this->data->list->sort
                ),
                $this->resolveDirection(
                    $this->data->list->direction
                ),
            );
    }
}
