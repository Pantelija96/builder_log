<?php

namespace App\DTO\TruckLog;

use Carbon\Carbon;
use Illuminate\Http\Request;

readonly class UpdateTruckLogData
{
    public function __construct(
        public ?Carbon $siteManagerStartedAt,
        public ?Carbon $siteManagerFinishedAt,
        public ?Carbon $operatorStartedAt,
        public ?Carbon $operatorFinishedAt,
        public ?float $startMileage,
        public ?float $endMileage,
        public ?float $fuelAdded,
        public ?float $fuelRemaining,
        public ?float $advancePayment,
        public ?string $noteSiteManager,
        public ?string $noteOperator,
        public array $providedFields,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            siteManagerStartedAt: $request->filled('site_manager_started_at')
                ? Carbon::parse($request->input('site_manager_started_at'))
                : null,

            siteManagerFinishedAt: $request->filled('site_manager_finished_at')
                ? Carbon::parse($request->input('site_manager_finished_at'))
                : null,

            operatorStartedAt: $request->filled('operator_started_at')
                ? Carbon::parse($request->input('operator_started_at'))
                : null,

            operatorFinishedAt: $request->filled('operator_finished_at')
                ? Carbon::parse($request->input('operator_finished_at'))
                : null,

            startMileage: $request->filled('start_mileage')
                ? (float) $request->input('start_mileage')
                : null,

            endMileage: $request->filled('end_mileage')
                ? (float) $request->input('end_mileage')
                : null,

            fuelAdded: $request->filled('fuel_added')
                ? (float) $request->input('fuel_added')
                : null,

            fuelRemaining: $request->filled('fuel_remaining')
                ? (float) $request->input('fuel_remaining')
                : null,

            advancePayment: $request->has('advance_payment')
                ? $request->float('advance_payment')
                : null,

            noteSiteManager: $request->input('note_site_manager'),
            noteOperator: $request->input('note_operator'),

            providedFields: $request->keys(),
        );
    }
}
