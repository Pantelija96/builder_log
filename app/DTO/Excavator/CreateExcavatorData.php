<?php

namespace App\DTO\Excavator;

use Illuminate\Http\Request;

readonly class CreateExcavatorData
{
    public function __construct(
        public int $machineId,
        public float $initialWorkHours,
    ) {}

    public static function fromRequest(Request $request,): self
    {
        return new self(
            machineId: $request->integer('machine_id'),
            initialWorkHours: $request->float('initial_work_hours', 0),
        );
    }
}
