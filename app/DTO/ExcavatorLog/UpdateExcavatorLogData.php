<?php

namespace App\DTO\ExcavatorLog;

use Illuminate\Http\Request;

readonly class UpdateExcavatorLogData
{
    public function __construct(
        public ?string $siteManagerStartedAt,
        public ?string $siteManagerFinishedAt,
        public ?string $operatorStartedAt,
        public ?string $operatorFinishedAt,
        public ?float $workHours,
        public ?float $fuelAdded,
        public ?float $fuelRemaining,
        public ?string $note,
        public array $providedFields,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            siteManagerStartedAt: $request->input(
                'site_manager_started_at'
            ),

            siteManagerFinishedAt: $request->input(
                'site_manager_finished_at'
            ),

            operatorStartedAt: $request->input(
                'operator_started_at'
            ),

            operatorFinishedAt: $request->input(
                'operator_finished_at'
            ),

            workHours: $request->has('work_hours')
                ? $request->float('work_hours')
                : null,

            fuelAdded: $request->has('fuel_added')
                ? $request->float('fuel_added')
                : null,

            fuelRemaining: $request->has('fuel_remaining')
                ? $request->float('fuel_remaining')
                : null,

            note: $request->has('note')
                ? $request->input('note')
                : null,

            providedFields: $request->keys(),
        );
    }
}
