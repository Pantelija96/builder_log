<?php

namespace App\QueryFilters;

use App\DTO\Task\GetTasksData;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class TaskFilter extends BaseFilter
{
    protected array $sortable = [
        'id',
        'title',
        'due_date',
        'created_at',
    ];

    protected string $defaultSort = 'created_at';

    public function __construct(
        private readonly GetTasksData $data,
    ) {
    }

    public function apply(Builder $query): Builder
    {
        return $query

            ->when(
                $this->data->search,
                function (Builder $query, string $search) {

                    $query->where(function (Builder $query) use ($search) {

                        $query
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");

                    });

                }
            )

            ->when(
                $this->data->title,
                fn (Builder $query, string $title)
                => $query->where('title', 'like', "%{$title}%")
            )

            ->when(
                $this->data->siteManagerId,
                fn (Builder $query, int $siteManagerId)
                => $query->where('site_manager_id', $siteManagerId)
            )

            ->when(
                $this->data->constructionSiteId,
                fn (Builder $query, int $constructionSiteId)
                => $query->where('construction_site_id', $constructionSiteId)
            )

            ->when(
                $this->data->createdBy,
                fn (Builder $query, int $createdBy)
                => $query->where('created_by', $createdBy)
            )

            ->when(
                ! is_null($this->data->completed),
                function (Builder $query) {

                    if ($this->data->completed) {
                        $query->whereNotNull('completed_at');
                    } else {
                        $query->whereNull('completed_at');
                    }

                }
            )

            ->when(
                ! is_null($this->data->read),
                function (Builder $query) {

                    if ($this->data->read) {
                        $query->whereNotNull('read_at');
                    } else {
                        $query->whereNull('read_at');
                    }

                }
            )

            ->when(
                $this->data->dueDateFrom,
                fn (Builder $query, Carbon $date)
                => $query->whereDate('due_date', '>=', $date)
            )

            ->when(
                $this->data->dueDateTo,
                fn (Builder $query, Carbon $date)
                => $query->whereDate('due_date', '<=', $date)
            )

            ->orderBy(
                $this->resolveSort($this->data->list->sort),
                $this->resolveDirection($this->data->list->direction),
            );
    }
}
