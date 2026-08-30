<?php

namespace App\DTO\ConstructionSite;

use App\Http\Requests\ConstructionSite\GetConstructionSiteStatisticsRequest;
use Carbon\Carbon;

readonly class GetConstructionSiteStatisticsData
{
    public function __construct(
        public Carbon $dateFrom,
        public Carbon $dateTo,
    ) {
    }

    public static function fromRequest(
        GetConstructionSiteStatisticsRequest $request,
    ): self {
        return new self(
            dateFrom: $request->date('date_from'),
            dateTo: $request->date('date_to'),
        );
    }
}
