<?php

namespace App\DTO\MachineAssignment;

use Illuminate\Http\Request;

readonly class CreateMachineAssignmentForOperatorData
{
    public function __construct(
        public int $constructionSiteId,
        public int $machineId,
    ) {
    }

    public static function fromRequest(Request $request,): self
    {
        return new self(
            constructionSiteId: $request->integer('construction_site_id'),
            machineId: $request->integer('machine_id'),
        );
    }
}
