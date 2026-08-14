<?php

namespace App\DTO\Excavator;

use Illuminate\Http\Request;

readonly class UpdateExcavatorData
{
    public function __construct(
        public float $initialWorkHours,
        public float $totalWorkHours,
    ) {}

    public static function fromRequest(Request $request,): self
    {
        return new self(
            initialWorkHours: $request->float('initial_work_hours'),
            totalWorkHours: $request->float('total_work_hours', 0),
        );
    }
}
