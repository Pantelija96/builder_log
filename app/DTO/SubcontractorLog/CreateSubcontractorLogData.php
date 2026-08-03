<?php

namespace App\DTO\SubcontractorLog;

use App\Http\Requests\SubcontractorLog\CreateSubcontractorLogRequest;
use Carbon\Carbon;

readonly class CreateSubcontractorLogData
{
    public function __construct(
        public int $subcontractorId,
        public int $workerCount,
        public ?Carbon $startedAt,
        public ?Carbon $finishedAt,
        public ?string $note,
    ) {
    }

    public static function fromRequest(CreateSubcontractorLogRequest $request): self
    {
        return new self(
            subcontractorId: $request->integer('subcontractor_id'),
            workerCount: $request->integer('worker_count'),
            startedAt: $request->date('started_at'),
            finishedAt: $request->date('finished_at'),
            note: $request->string('note')->toString() ?: null,
        );
    }
}
