<?php

namespace App\DTO\ExcavatorLog;

use App\Http\Requests\ExcavatorLog\UpdateExcavatorLogRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;

readonly class UpdateExcavatorLogData
{
    public function __construct(
        public ?Carbon $siteManagerStartedAt,
        public ?Carbon $siteManagerFinishedAt,

        public ?Carbon $operatorStartedAt,
        public ?Carbon $operatorFinishedAt,

        public ?float $workHours,
        public ?float $startWorkHours,
        public ?float $finishWorkHours,

        public ?float $fuelAdded,
        public ?float $fuelRemaining,

        public ?string $noteSiteManager,
        public ?string $noteOperator,

        public array $providedFields,
    ) {}

    public static function fromRequest(UpdateExcavatorLogRequest $request,): self
    {
        return new self(
            siteManagerStartedAt: $request->filled('site_manager_started_at') ? Carbon::parse($request->input('site_manager_started_at')) : null,
            siteManagerFinishedAt: $request->filled('site_manager_finished_at') ? Carbon::parse($request->input('site_manager_finished_at')) : null,

            operatorStartedAt: $request->filled('operator_started_at') ? Carbon::parse($request->input('operator_started_at')) : null,
            operatorFinishedAt: $request->filled('operator_finished_at') ? Carbon::parse($request->input('operator_finished_at')) : null,

            workHours: $request->has('work_hours') ? $request->float('work_hours') : null,
            startWorkHours: $request->has('start_work_hours') ? $request->float('start_work_hours') : null,
            finishWorkHours: $request->has('finish_work_hours') ? $request->float('finish_work_hours') : null,

            fuelAdded: $request->has('fuel_added') ? $request->float('fuel_added') : null,
            fuelRemaining: $request->has('fuel_remaining') ? $request->float('fuel_remaining') : null,

            noteSiteManager: $request->has('note_site_manager') ? $request->input('note_site_manager') : null,
            noteOperator: $request->has('note_operator') ? $request->input('note_operator') : null,

            providedFields: $request->keys(),
        );
    }
}
