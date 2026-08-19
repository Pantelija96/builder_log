<?php

namespace App\DTO\WorkerAttendance;

use App\DTO\Requests\ListQueryData;
use App\Http\Requests\WorkerAttendance\GetWorkerAttendancesRequest;
use Carbon\Carbon;

readonly class GetWorkerAttendancesData
{
    public function __construct(
        public ListQueryData $list,
        public ?int $workerId,
        public ?Carbon $dateCreatedFrom,
        public ?Carbon $dateCreatedTo,
    ) {}

    public static function fromRequest(GetWorkerAttendancesRequest $request,): self {
        return new self(
            list: ListQueryData::fromRequest($request),
            workerId: $request->integer('worker_id') ?: null,
            dateCreatedFrom: $request->filled('date_created_from') ? Carbon::parse($request->date_created_from) : null,
            dateCreatedTo: $request->filled('date_created_to') ? Carbon::parse($request->date_created_to) : null,
        );
    }
}
