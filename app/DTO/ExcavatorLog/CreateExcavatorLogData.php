<?php

namespace App\DTO\ExcavatorLog;

use App\Http\Requests\ExcavatorLog\CreateExcavatorLogRequest;
use Carbon\Carbon;

readonly class CreateExcavatorLogData
{
    public function __construct(
        public int $machineId,
        public int $workerId,
        public ?Carbon $siteManagerStartedAt,
        public ?Carbon $siteManagerFinishedAt,
        public ?string $noteSiteManager,
    ) {
    }

    public static function fromRequest(CreateExcavatorLogRequest $request,): self
    {
        return new self(
            machineId: $request->integer('machine_id'),
            workerId: $request->integer('worker_id'),
            siteManagerStartedAt: $request->validated('site_manager_started_at') ? Carbon::parse($request->validated('site_manager_started_at')) : null,
            siteManagerFinishedAt: $request->validated('site_manager_finished_at') ? Carbon::parse($request->validated('site_manager_finished_at')) : null,
            noteSiteManager: $request->validated('note_site_manager'),
        );
    }
}
