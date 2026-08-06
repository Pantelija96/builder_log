<?php

namespace App\DTO\CashAdvance;

use App\DTO\Requests\ListQueryData;
use App\Http\Requests\CashAdvance\GetCashAdvancesRequest;
use Carbon\Carbon;

readonly class GetCashAdvancesData
{
    public function __construct(
        public ListQueryData $list,

        public ?int $siteManagerId,

        public ?Carbon $dateFrom,

        public ?Carbon $dateTo,

        public ?float $minAmount,

        public ?float $maxAmount,
    ) {
    }

    public static function fromRequest(
        GetCashAdvancesRequest $request,
    ): self {

        return new self(

            list: ListQueryData::fromRequest($request),

            siteManagerId: $request->integer('site_manager_id') ?: null,

            dateFrom: $request->filled('date_from')
                ? Carbon::parse($request->date_from)
                : null,

            dateTo: $request->filled('date_to')
                ? Carbon::parse($request->date_to)
                : null,

            minAmount: $request->filled('min_amount')
                ? $request->float('min_amount')
                : null,

            maxAmount: $request->filled('max_amount')
                ? $request->float('max_amount')
                : null,
        );
    }
}
