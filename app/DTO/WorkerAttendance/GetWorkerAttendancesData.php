<?php

namespace App\DTO\WorkerAttendance;

use App\DTO\Requests\ListQueryData;
use App\Http\Requests\WorkerAttendance\GetWorkerAttendancesRequest;

readonly class GetWorkerAttendancesData
{
    public function __construct(public ListQueryData $list, public ?int $workerId,) {
    }

    public static function fromRequest(GetWorkerAttendancesRequest $request,): self {
        return new self(
            list: ListQueryData::fromRequest($request),

            workerId: $request->integer('worker_id')
                ?: null,
        );
    }
}
