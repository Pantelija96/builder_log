<?php

namespace App\DTO\WorkerPayment;

use App\DTO\Requests\ListQueryData;
use App\Http\Requests\WorkerPayment\GetWorkerPaymentsRequest;
use Carbon\Carbon;

readonly class GetWorkerPaymentsData
{
    public function __construct(
        public ListQueryData $list,
        public ?int $workerId,
        public ?Carbon $dateFrom,
        public ?Carbon $dateTo,
    ) {
    }

    public static function fromRequest(
        GetWorkerPaymentsRequest $request
    ): self {
        return new self(
            list: ListQueryData::fromRequest($request),

            workerId: $request->integer('worker_id') ?: null,

            dateFrom: $request->filled('date_from')
                ? Carbon::parse($request->input('date_from'))
                : null,

            dateTo: $request->filled('date_to')
                ? Carbon::parse($request->input('date_to'))
                : null,
        );
    }
}
