<?php

namespace App\QueryFilters;

use App\DTO\Note\GetNotesData;
use Illuminate\Database\Eloquent\Builder;

class NoteFilter extends BaseFilter
{
    protected array $sortable = [
        'id',
        'date',
        'created_at',
    ];

    protected string $defaultSort = 'date';

    public function __construct(
        private readonly GetNotesData $data,
    ) {
    }

    public function apply(Builder $query): Builder
    {
        return $query

            ->when(
                $this->data->dailyLogId,
                fn (Builder $query, int $dailyLogId) =>
                $query->where('daily_log_id', $dailyLogId)
            )

            ->when(
                $this->data->notifyAdmin !== null,
                fn (Builder $query) =>
                $query->where(
                    'notify_admin',
                    $this->data->notifyAdmin
                )
            )

            ->when(
                $this->data->createdBy,
                fn (Builder $query, int $createdBy) =>
                $query->where('created_by', $createdBy)
            )

            ->when(
                $this->data->list->search,
                function (Builder $query, string $search) {

                    $query->where(
                        'note',
                        'like',
                        "%{$search}%"
                    );

                }
            )

            ->orderBy(
                $this->resolveSort($this->data->list->sort),
                $this->resolveDirection($this->data->list->direction),
            );
    }
}
