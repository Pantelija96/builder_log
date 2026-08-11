<?php

namespace App\DTO\MachineAssignment;

use App\Http\Requests\Machine\CreateMachineAssignmentRequest;
use Carbon\Carbon;

readonly class CreateMachineAssignmentData
{
    public function __construct(
        public int $machineId,
        public Carbon $siteManagerStartedAt,
        public ?Carbon $siteManagerFinishedAt,
    ) {
    }

    public static function fromRequest(
        CreateMachineAssignmentRequest $request,
    ): self {
        return new self(
            machineId: (int) $request->validated('machine_id'),

            siteManagerStartedAt: Carbon::parse(
                $request->validated('site_manager_started_at')
            ),

            siteManagerFinishedAt: $request->validated(
                'site_manager_finished_at'
            )
                ? Carbon::parse(
                    $request->validated('site_manager_finished_at')
                )
                : null,
        );
    }
}
