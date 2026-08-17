<?php

namespace App\DTO\Expense;

use App\DTO\Requests\ListQueryData;
use App\Http\Requests\Expense\GetExpensesRequest;
use Carbon\Carbon;

readonly class GetExpensesData
{
    public function __construct(
        public ListQueryData $list,
        public ?string       $search,
        public ?string       $title,
        public ?int          $createdBy,
        public ?Carbon       $dateFrom,
        public ?Carbon       $dateTo,
        public ?float        $minAmount,
        public ?float        $maxAmount,
        public ?int          $constructionSiteId,
        public ?int          $siteManagerId,
        public ?Carbon       $dateCreatedFrom,
        public ?Carbon       $dateCreatedTo,
    )
    {
    }

    public static function fromRequest(GetExpensesRequest $request): self
    {
        return new self(
            list: ListQueryData::fromRequest($request),
            search: $request->filled('search') ? $request->string('search')->toString() : null,
            title: $request->filled('title') ? $request->string('title')->toString() : null,
            createdBy: $request->integer('created_by') ?: null,
            dateFrom: $request->filled('date_from') ? Carbon::parse($request->date_from) : null,
            dateTo: $request->filled('date_to') ? Carbon::parse($request->date_to) : null,
            minAmount: $request->filled('min_amount') ? $request->float('min_amount') : null,
            maxAmount: $request->filled('max_amount') ? $request->float('max_amount') : null,
            constructionSiteId: $request->integer('construction_site_id') ?: null,
            siteManagerId: $request->integer('site_manager_id') ?: null,
            dateCreatedFrom: $request->filled('date_created_from') ? Carbon::parse($request->date_created_from) : null,
            dateCreatedTo: $request->filled('date_created_to') ? Carbon::parse($request->date_created_to) : null,
        );
    }
}
