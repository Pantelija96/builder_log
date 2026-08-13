<?php

namespace App\DTO\MachineAssignment;

use App\Http\Requests\MachineAssignment\CreateMachineAssignmentRequest;

readonly class CreateMachineAssignmentData
{
    public function __construct(
        public int $machineId,
        public int $workerId,
    ) {}

    public static function fromRequest(CreateMachineAssignmentRequest $request,): self
    {
        return new self(
            machineId: $request->integer('machine_id'),
            workerId: $request->integer('worker_id'),
        );
    }
}
