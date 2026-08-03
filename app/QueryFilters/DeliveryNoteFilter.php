<?php

namespace App\QueryFilters;

use App\DTO\DeliveryNote\GetDeliveryNotesData;
use Illuminate\Database\Eloquent\Builder;

class DeliveryNoteFilter extends BaseFilter
{
    protected array $sortable = [
        'id',
        'name',
        'date',
        'created_at',
    ];

    protected string $defaultSort = 'date';

    public function __construct(
        private readonly GetDeliveryNotesData $data,
    ) {
    }

    public function apply(Builder $query): Builder
    {
        return $query
            ->when($this->data->dailyLogId,
                fn (Builder $query, int $dailyLogId) => $query->where('daily_log_id', $dailyLogId))
            ->when($this->data->supplierId,
                fn (Builder $query, int $supplierId) => $query->where('supplier_id', $supplierId))
            ->when(
                $this->data->list->search,
                function (Builder $query, string $search) {

                    $query->where(function (Builder $query) use ($search) {
                        $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
                }
            )
            ->when(
                $this->data->name,
                fn (Builder $query, string $name) => $query->where('name', 'like', "%{$name}%")
            )
            ->orderBy(
                $this->resolveSort($this->data->list->sort),
                $this->resolveDirection($this->data->list->direction),
            );
    }
}
