<?php

namespace App\QueryFilters;

use App\DTO\SubcontractorLog\GetSubcontractorLogsData;
use Illuminate\Database\Eloquent\Builder;

class SubcontractorLogFilter extends BaseFilter
{
    protected array $sortable = [
        'id',
        'worker_count',
        'started_at',
        'finished_at',
        'created_at',
    ];

    protected string $defaultSort = 'created_at';

    public function __construct(
        private readonly GetSubcontractorLogsData $data,
    ) {
    }

    public function apply(Builder $query): Builder
    {
        return $query

            ->when(
                $this->data->subcontractorId,
                fn (Builder $query, int $id) => $query->where(
                    'subcontractor_id',
                    $id
                )
            )

            ->when(
                $this->data->list->search,
                function (Builder $query, string $search) {

                    $query->whereHas(
                        'subcontractor',
                        function (Builder $query) use ($search) {

                            $query->where(
                                'name',
                                'like',
                                "%{$search}%"
                            );
                        }
                    );
                }
            )

            ->orderBy(
                $this->resolveSort($this->data->list->sort),
                $this->resolveDirection($this->data->list->direction),
            );
    }
}
