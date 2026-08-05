<?php

namespace App\QueryFilters;

use App\DTO\Notification\GetNotificationsData;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class NotificationFilter extends BaseFilter
{
    protected array $sortable = [
        'id',
        'title',
        'type',
        'created_at',
    ];

    protected string $defaultSort = 'created_at';

    public function __construct(
        private readonly GetNotificationsData $data,
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
                            ->orWhere('message', 'like', "%{$search}%");

                    });

                }
            )

            ->when(
                $this->data->type,
                fn (Builder $query, string $type)
                => $query->where('type', $type)
            )

            ->when(
                ! is_null($this->data->isRead),
                function (Builder $query) {

                    if ($this->data->isRead) {
                        $query->whereNotNull('read_at');
                    } else {
                        $query->whereNull('read_at');
                    }

                }
            )

            ->when(
                $this->data->createdFrom,
                fn (Builder $query, Carbon $date)
                => $query->whereDate('created_at', '>=', $date)
            )

            ->when(
                $this->data->createdTo,
                fn (Builder $query, Carbon $date)
                => $query->whereDate('created_at', '<=', $date)
            )

            ->orderBy(
                $this->resolveSort($this->data->list->sort),
                $this->resolveDirection($this->data->list->direction),
            );
    }
}
