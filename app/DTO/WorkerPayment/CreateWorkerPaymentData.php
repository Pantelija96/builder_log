<?php

namespace App\DTO\WorkerPayment;

use App\Http\Requests\WorkerPayment\CreateWorkerPaymentRequest;
use Carbon\Carbon;

readonly class CreateWorkerPaymentData
{
    public function __construct(
        public int $workerId,
        public float $amount,
        public Carbon $date,
        public ?string $note,
    ) {
    }

    public static function fromRequest(
        CreateWorkerPaymentRequest $request
    ): self {
        return new self(
            workerId: $request->integer('worker_id'),
            amount: $request->float('amount'),
            date: Carbon::parse($request->input('date')),
            note: $request->filled('note')
                ? $request->string('note')->toString()
                : null,
        );
    }
}
