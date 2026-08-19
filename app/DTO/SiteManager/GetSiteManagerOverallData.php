<?php

namespace App\DTO\SiteManager;

use App\Http\Requests\SiteManager\GetSiteManagerOverallRequest;
use Carbon\Carbon;

readonly class GetSiteManagerOverallData
{
    public function __construct(
        public ?Carbon $dateCreatedFrom,
        public ?Carbon $dateCreatedTo,
    )
    {}

    public static function fromRequest(GetSiteManagerOverallRequest $request,): self
    {
        return new self(
            dateCreatedFrom: $request->filled('date_created_from') ? Carbon::parse($request->date_created_from)->startOfDay() : null,
            dateCreatedTo: $request->filled('date_created_to') ? Carbon::parse($request->date_created_to)->startOfDay() : null,
        );
    }
}
