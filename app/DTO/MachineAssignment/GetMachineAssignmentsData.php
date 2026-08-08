<?php

namespace App\DTO\MachineAssignment;

use App\DTO\Requests\ListQueryData;
use App\Http\Requests\Machine\GetMachineAssignmentsRequest;
use Carbon\Carbon;

readonly class GetMachineAssignmentsData
{
    public function __construct(
        public Carbon $date,
        public int $constructionSiteId,
        public int $siteManagerId,
        public ListQueryData $list,
    ) {
    }

    public static function fromRequest(
        GetMachineAssignmentsRequest $request,
    ): self {
        return new self(
            date: Carbon::parse(
                $request->validated('date')
            ),
            constructionSiteId: (int) $request->validated(
                'construction_site_id'
            ),
            siteManagerId: (int) $request->validated(
                'site_manager_id'
            ),
            list: ListQueryData::fromRequest($request),
        );
    }
}
