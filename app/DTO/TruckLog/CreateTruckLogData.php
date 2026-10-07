<?php

namespace App\DTO\TruckLog;

use Carbon\Carbon;
use Illuminate\Http\Request;

readonly class CreateTruckLogData
{
    public function __construct(
        public int $machineId,
        public int $workerId,
        public ?Carbon $siteManagerStartedAt,
        public ?Carbon $siteManagerFinishedAt,
        public ?string $noteSiteManager,
        public float $advancePayment,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            machineId: $request->integer('machine_id'),
            workerId: $request->integer('worker_id'),
            siteManagerStartedAt: $request->filled('site_manager_started_at') ? Carbon::parse($request->input('site_manager_started_at')) : null,
            siteManagerFinishedAt: $request->filled('site_manager_finished_at') ? Carbon::parse($request->input('site_manager_finished_at')) : null,
            noteSiteManager: $request->input('note_site_manager'),
            advancePayment: $request->float('advance_payment', 0),
        );
    }
}
