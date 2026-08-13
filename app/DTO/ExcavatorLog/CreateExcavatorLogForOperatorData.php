<?php

namespace App\DTO\ExcavatorLog;

use App\Http\Requests\ExcavatorLog\CreateExcavatorLogForOperatorRequest;
use Carbon\Carbon;

readonly class CreateExcavatorLogForOperatorData
{
    public function __construct(
        public int $machineId,
        public int $constructionSiteId,
        public ?Carbon $operatorStartedAt,
        public ?Carbon $operatorFinishedAt,
        public ?string $noteOperator,
    ) {
    }

    public static function fromRequest(CreateExcavatorLogForOperatorRequest $request,): self
    {
        return new self(
            machineId: $request->integer('machine_id'),
            constructionSiteId: $request->integer('construction_site_id'),
            operatorStartedAt: $request->validated('operator_started_at') ? Carbon::parse($request->validated('operator_started_at')) : null,
            operatorFinishedAt: $request->validated('operator_finished_at') ? Carbon::parse($request->validated('operator_finished_at')) : null,
            noteOperator: $request->validated('note_operator'),
        );
    }
}
