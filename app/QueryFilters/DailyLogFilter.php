<?php

namespace App\QueryFilters;

use App\DTO\DailyLog\GetDailyLogsData;
use Illuminate\Database\Eloquent\Builder;

class DailyLogFilter extends BaseFilter
{
    public const SORTABLE = [
        'id',
        'date',
        'created_at',
        'updated_at',
    ];

    protected array $sortable = self::SORTABLE;

    public function __construct(
        protected readonly GetDailyLogsData $data,
    ) {
    }

    public function apply(Builder $query): Builder
    {
        $search = $this->data->list->search;

        $sort = $this->resolveSort(
            $this->data->list->sort
        );

        return $query
            ->when(
                $search,
                function (Builder $query) use ($search) {
                    $query->where(function (Builder $query) use ($search) {
                        $query

                            ->whereHas(
                                'constructionSite',
                                fn (Builder $builder) => $builder
                                    ->where(
                                        'name',
                                        'like',
                                        "%{$search}%"
                                    )
                            )

                            ->orWhereHas(
                                'siteManager',
                                fn (Builder $builder) => $builder
                                    ->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%")
                            );
                    });
                }
            )

            ->when(
                $this->data->companyId,
                fn (Builder $query) => $query->where(
                    'company_id',
                    $this->data->companyId
                )
            )

            ->when(
                $this->data->constructionSiteId,
                fn (Builder $query) => $query->where(
                    'construction_site_id',
                    $this->data->constructionSiteId
                )
            )

            ->when(
                $this->data->siteManagerId,
                fn (Builder $query) => $query->where(
                    'site_manager_id',
                    $this->data->siteManagerId
                )
            )

            ->when(
                $this->data->date,
                fn (Builder $query) => $query->whereDate(
                    'date',
                    $this->data->date
                )
            )

            ->when(
                ! is_null($this->data->isLocked),
                fn (Builder $query) => $query->where(
                    'is_locked',
                    $this->data->isLocked
                )
            )

            ->orderBy(
                $sort,
                $this->resolveDirection(
                    $this->data->list->direction
                )
            );
    }
}
