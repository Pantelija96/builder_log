<?php

namespace App\DTO\SubcontractorLog;

use App\Http\Requests\SubcontractorLog\UpdateSubcontractorLogRequest;
use Carbon\Carbon;

readonly class UpdateSubcontractorLogData
{
    public function __construct(public int $workerCount, public ?Carbon $startedAt, public ?Carbon $finishedAt, public ?string $note,
    ) {}

    public static function fromRequest(UpdateSubcontractorLogRequest $request): self
    {
        return new self(
            workerCount: $request->integer('worker_count'),
            startedAt: $request->date('started_at'),
            finishedAt: $request->date('finished_at'),
            note: $request->string('note')->toString() ?: null,
        );
    }
}
