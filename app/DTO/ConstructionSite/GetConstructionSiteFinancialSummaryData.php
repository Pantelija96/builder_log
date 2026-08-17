<?php

namespace App\DTO\ConstructionSite;

use App\Http\Requests\ConstructionSite\GetConstructionSiteFinancialSummaryRequest;
use Carbon\Carbon;

readonly class GetConstructionSiteFinancialSummaryData
{
    public function __construct(
        public Carbon $dateFrom,
        public Carbon $dateTo,)
    {}

    public static function fromRequest(GetConstructionSiteFinancialSummaryRequest $request,): self
    {
        return new self(
            dateFrom: Carbon::parse($request->date_from),
            dateTo: Carbon::parse($request->date_to),
        );
    }
}
