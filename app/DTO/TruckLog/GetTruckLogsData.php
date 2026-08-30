<?php

namespace App\DTO\TruckLog;

use App\DTO\Requests\ListQueryData;
use App\Http\Requests\TruckLog\GetTruckLogsRequest;

readonly class GetTruckLogsData
{
    public function __construct(
        public ?int $machineId,
        public ?int $workerId,
        public ?string $dateFrom,
        public ?string $dateTo,
        public ListQueryData $list,
    ) {}

    public static function fromRequest(GetTruckLogsRequest $request): self
    {
        return new self(
            machineId: $request->filled('machine_id') ? $request->integer('machine_id') : null,
            workerId: $request->filled('worker_id') ? $request->integer('worker_id') : null,
            dateFrom: $request->filled('date_from') ? $request->date('date_from')->toDateString() : null,
            dateTo: $request->filled('date_to') ? $request->date('date_to')->toDateString() : null,
            list: ListQueryData::fromRequest($request),
        );
    }
}
