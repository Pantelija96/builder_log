<?php

namespace App\DTO\WorkerAttendance;

use App\Http\Requests\WorkerAttendance\CreateWorkerAttendanceRequest;
use Carbon\Carbon;

readonly class CreateWorkerAttendanceData
{
    public function __construct(
        public int $workerId,
        public Carbon $startedAt,
        public ?Carbon $finishedAt,
        public float $advancePayment,
    ) {
    }

    public static function fromRequest(
        CreateWorkerAttendanceRequest $request,
    ): self {
        return new self(
            workerId: $request->integer('worker_id'),
            startedAt: Carbon::parse($request->input('started_at')),
            finishedAt: $request->filled('finished_at')
                ? Carbon::parse($request->input('finished_at'))
                : null,
            advancePayment: $request->float('advance_payment', 0),
        );
    }
}
