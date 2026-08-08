<?php

namespace App\DTO\MachineAssignment;

use App\Http\Requests\Machine\UpdateMachineAssignmentRequest;
use Carbon\Carbon;

readonly class UpdateMachineAssignmentData
{
    public function __construct(
        public Carbon $startedAt,
        public ?Carbon $finishedAt,
    ) {
    }

    public static function fromRequest(
        UpdateMachineAssignmentRequest $request,
    ): self {
        return new self(
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
