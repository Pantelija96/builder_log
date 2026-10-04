<?php

namespace App\DTO\TruckLog;

use Carbon\Carbon;
use Illuminate\Http\Request;

readonly class CreateTruckLogForDriverData
{
    public function __construct(
        public int $machineId,
        public int $constructionSiteId,
        public ?Carbon $operatorStartedAt,
        public ?Carbon $operatorFinishedAt,
        public ?string $noteOperator,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            machineId: $request->integer('machine_id'),
            constructionSiteId: $request->integer('construction_site_id'),
            operatorStartedAt: $request->filled('operator_started_at') ? Carbon::parse($request->input('operator_started_at')) : null,
            operatorFinishedAt: $request->filled('operator_finished_at') ? Carbon::parse($request->input('operator_finished_at')) : null,
            noteOperator: $request->input('note_operator'),
        );
    }
}
