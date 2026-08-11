<?php

namespace App\DTO\TruckLog;

use Illuminate\Http\Request;

readonly class CreateTruckLogData
{
    public function __construct(
        public int $machineId,
        public ?int $workerId,
        public string $date,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            machineId: $request->integer('machine_id'),
            workerId: $request->filled('worker_id') ? $request->integer('worker_id') : null,
            date: $request->date('date')->toDateString(),
        );
    }
}
