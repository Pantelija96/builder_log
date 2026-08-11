<?php

namespace App\DTO\ExcavatorLog;

use Illuminate\Http\Request;

readonly class CreateExcavatorLogData
{
    public function __construct(
        public int $machineAssignmentId,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            machineAssignmentId: $request->integer(
                'machine_assignment_id'
            ),
        );
    }
}
