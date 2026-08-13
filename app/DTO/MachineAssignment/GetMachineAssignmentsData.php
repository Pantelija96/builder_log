<?php

namespace App\DTO\MachineAssignment;

use App\DTO\Requests\ListQueryData;
use App\Http\Requests\MachineAssignment\GetMachineAssignmentsRequest;
use Carbon\Carbon;

readonly class GetMachineAssignmentsData
{
    public function __construct(
        public Carbon $date,
        public int $workerId,
        public int $constructionSiteId,
        public int $siteManagerId,
        public ListQueryData $list,
    ) {
    }

    public static function fromRequest(GetMachineAssignmentsRequest $request,): self
    {
        return new self(
            date: Carbon::parse($request->validated('date')),
            workerId: (int) $request->validated('worker_id'),
            constructionSiteId: (int) $request->validated('construction_site_id'),
            siteManagerId: (int) $request->validated('site_manager_id'),
            list: ListQueryData::fromRequest($request),
        );
    }
}
