<?php

namespace App\DTO\Worker;

use App\Http\Requests\Worker\GetWorkerWorkHistoryRequest;
use Carbon\Carbon;

readonly class GetWorkerWorkHistoryData
{
    public function __construct(
        public ?Carbon $dateFrom,
        public ?Carbon $dateTo,
    ) {}

    public static function fromRequest(
        GetWorkerWorkHistoryRequest $request,
    ): self {
        return new self(
            dateFrom: $request->filled('date_from') ? Carbon::parse($request->input('date_from')) : null,
            dateTo: $request->filled('date_to') ? Carbon::parse($request->input('date_to')) : null,
        );
    }
}
