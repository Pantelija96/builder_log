<?php

namespace App\QueryFilters;

use App\DTO\Document\GetDocumentsData;
use Illuminate\Database\Eloquent\Builder;

class DocumentFilter extends BaseFilter
{
    protected array $sortable = [
        'id',
        'name',
        'type',
        'created_at',
        'size',
    ];

    protected string $defaultSort = 'created_at';

    public function __construct(
        private readonly GetDocumentsData $data,
    ) {
    }

    public function apply(
        Builder $query,
    ): Builder {

        return $query

            ->when(
                $this->data->search,
                fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('original_name', 'like', "%{$search}%");
                })
            )

            ->when(
                $this->data->name,
                fn (Builder $query, string $name) => $query->where(
                    'name',
                    'like',
                    "%{$name}%"
                )
            )

            ->when(
                $this->data->uploadedBy,
                fn (Builder $query, int $uploadedBy) => $query->where(
                    'uploaded_by',
                    $uploadedBy,
                )
            )

            ->when(
                $this->data->type,
                fn (Builder $query, $type) => $query->where(
                    'type',
                    $type,
                )
            )

            ->when(
                $this->data->siteManagerId,
                fn (Builder $query, int $id) => $query->where(
                    'site_manager_id',
                    $id,
                )
            )

            ->when(
                $this->data->dateFrom,
                fn (Builder $query, $date) => $query->whereDate(
                    'created_at',
                    '>=',
                    $date,
                )
            )

            ->when(
                $this->data->dateTo,
                fn (Builder $query, $date) => $query->whereDate(
                    'created_at',
                    '<=',
                    $date,
                )
            )

            ->orderBy(
                $this->resolveSort(
                    $this->data->list->sort,
                ),
                $this->resolveDirection(
                    $this->data->list->direction,
                ),
            );
    }
}
