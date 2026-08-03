<?php

namespace App\DTO\WorkerAttendance;

use App\Http\Requests\WorkerAttendance\UpdateWorkerAttendanceRequest;
use Illuminate\Support\Carbon;

readonly class UpdateWorkerAttendanceData
{
    public function __construct(
        public ?Carbon $startedAt = null,
        public ?Carbon $finishedAt = null,
        public ?float $advancePayment = null,
    ) {}

    public static function fromRequest(UpdateWorkerAttendanceRequest $request): self
    {
        return new self(
            startedAt: $request->filled('started_at')
                ? Carbon::parse($request->started_at)
                : null,

            finishedAt: $request->filled('finished_at')
                ? Carbon::parse($request->finished_at)
                : null,

            advancePayment: $request->filled('advance_payment')
                ? (float) $request->advance_payment
                : null,
        );
    }
    public function toUpdateArray(): array
    {
        return array_filter([
            'started_at'      => $this->startedAt,
            'finished_at'     => $this->finishedAt,
            'advance_payment' => $this->advancePayment,
        ], fn ($value) => $value !== null);
    }
}
