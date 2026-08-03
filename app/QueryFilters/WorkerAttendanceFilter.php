<?php

namespace App\QueryFilters;

use App\DTO\WorkerAttendance\GetWorkerAttendancesData;
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

    public function __construct(protected readonly GetWorkerAttendancesData $data,
    ) {
    }

    public function apply(Builder $query,): Builder {

        if ($this->data->workerId) {
            $query->where(
                'worker_id',
                $this->data->workerId
            );
        }

        if ($search = $this->data->list->search) {

            $query->where(function ($query) use ($search) {

                $query->whereHas(
                    'worker',
                    function ($query) use ($search) {

                        $query
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    }
                );
            });
        }

        return $query
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
