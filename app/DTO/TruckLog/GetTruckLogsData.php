<?php

namespace App\DTO\TruckLog;

use App\DTO\Requests\ListQueryData;
use Illuminate\Foundation\Http\FormRequest;

readonly class GetTruckLogsData
{
    public function __construct(
        public ?int $machineId,
        public ?int $workerId,
        public ?string $date,
        public ListQueryData $list,
    ) {
    }

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            machineId: $request->filled('machine_id') ? $request->integer('machine_id') : null,
            workerId: $request->filled('worker_id') ? $request->integer('worker_id') : null,
            date: $request->filled('date') ? $request->date('date')->toDateString() : null,
            list: ListQueryData::fromRequest($request),
        );
    }
}
