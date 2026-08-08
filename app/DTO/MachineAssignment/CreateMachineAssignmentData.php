<?php

namespace App\DTO\MachineAssignment;

use App\Http\Requests\Machine\CreateMachineAssignmentRequest;
use Carbon\Carbon;

readonly class CreateMachineAssignmentData
{
    public function __construct(
        public int $machineId,
        public Carbon $startedAt,
        public ?Carbon $finishedAt,
    ) {
    }

    public static function fromRequest(
        CreateMachineAssignmentRequest $request,
    ): self {
        return new self(
            machineId: (int) $request->validated('machine_id'),
            startedAt: Carbon::parse(
                $request->validated('started_at')
            ),
            finishedAt: $request->validated('finished_at')
                ? Carbon::parse(
                    $request->validated('finished_at')
                )
                : null,
        );
    }
}
