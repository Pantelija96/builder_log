<?php

namespace App\QueryFilters;

use App\DTO\Note\GetNotesData;
use Illuminate\Database\Eloquent\Builder;

class NoteFilter extends BaseFilter
{
    //Available filters: daily_log_id, construction_site_id, site_manager_id, date_from, date_to, notify_admin, created_by, search, sort, direction
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
        $query
            ->when(
                $this->data->dailyLogId,
                fn (Builder $query, int $dailyLogId) =>
                $query->where('daily_log_id', $dailyLogId)
            )

            ->when(
                $this->data->constructionSiteId,
                fn (Builder $query, int $constructionSiteId) =>
                $query->where('construction_site_id', $constructionSiteId)
            )

            ->when(
                $this->data->siteManagerId,
                fn (Builder $query, int $siteManagerId) =>
                $query->where('site_manager_id', $siteManagerId)
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
            );

        $query = $this->applyCreatedAtFilter(
            query: $query,
            from: $this->data->dateCreatedFrom,
            to: $this->data->dateCreatedTo,
        );

        return $query->orderBy(
            $this->resolveSort($this->data->list->sort),
            $this->resolveDirection($this->data->list->direction),
        );
    }
}
