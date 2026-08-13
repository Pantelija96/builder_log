<?php

namespace App\DTO\Excavator;

use Illuminate\Http\Request;

readonly class UpdateExcavatorData
{
    public function __construct(
        public float $initialWorkHours,
    ) {}

    public static function fromRequest(Request $request,): self
    {
        return new self(
            initialWorkHours: $request->float(
                'initial_work_hours'
            ),
        );
    }
}
