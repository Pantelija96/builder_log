<?php

namespace App\DTO\TruckLog;

use Illuminate\Http\Request;

readonly class UpdateTruckLogData
{
    public function __construct(
        public ?string $siteManagerStartedAt,
        public ?string $siteManagerFinishedAt,
        public ?string $operatorStartedAt,
        public ?string $operatorFinishedAt,
        public ?float $startMileage,
        public ?float $endMileage,
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

            startMileage: $request->has('start_mileage')
                ? $request->float('start_mileage')
                : null,

            endMileage: $request->has('end_mileage')
                ? $request->float('end_mileage')
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
