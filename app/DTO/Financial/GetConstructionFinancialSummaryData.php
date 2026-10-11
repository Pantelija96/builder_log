<?php

namespace App\DTO\Financial;

use App\Http\Requests\Financial\GetConstructionFinancialSummaryRequest;
use Carbon\Carbon;

readonly class GetConstructionFinancialSummaryData
{
    public function __construct(
        public Carbon $dateFrom,
        public Carbon $dateTo,
    ) {
    }

    public static function fromRequest(GetConstructionFinancialSummaryRequest $request,): self {
        return new self(
            dateFrom: Carbon::parse($request->validated('date_from')),
            dateTo: Carbon::parse($request->validated('date_to')),
        );
    }
}
