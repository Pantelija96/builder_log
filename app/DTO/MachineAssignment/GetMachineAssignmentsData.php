<?php

namespace App\DTO\MachineAssignment;

use App\DTO\Requests\ListQueryData;
use App\Enums\MachineType;
use App\Http\Requests\MachineAssignment\GetMachineAssignmentsRequest;
use Carbon\Carbon;

readonly class GetMachineAssignmentsData
{
    public function __construct(
        public ?Carbon $date,
        public ?Carbon $dateFrom,
        public ?Carbon $dateTo,
        public ?int $machineId,
        public ?MachineType $machineType,
        public ?int $workerId,
        public ?int $constructionSiteId,
        public ?int $siteManagerId,
        public ?int $createdBy,
        public ?bool $deleted,
        public ListQueryData $list,
    ) {
    }

    public static function fromRequest(
        GetMachineAssignmentsRequest $request,
    ): self {
        return new self(
            date: $request->filled('date') ? Carbon::parse($request->validated('date')) : null,
            dateFrom: $request->filled('date_from') ? Carbon::parse($request->validated('date_from')) : null,
            dateTo: $request->filled('date_to') ? Carbon::parse($request->validated('date_to')) : null,
            machineId: $request->filled('machine_id') ? (int) $request->validated('machine_id') : null,
            machineType: $request->enum('machine_type', MachineType::class,),
            workerId: $request->filled('worker_id') ? (int) $request->validated('worker_id') : null,
            constructionSiteId: $request->filled('construction_site_id') ? (int) $request->validated('construction_site_id') : null,
            siteManagerId: $request->filled('site_manager_id') ? (int) $request->validated('site_manager_id') : null,
            createdBy: $request->filled('created_by') ? (int) $request->validated('created_by') : null,
            deleted: $request->boolean('deleted'),
            list: ListQueryData::fromRequest($request),
        );
    }
}
