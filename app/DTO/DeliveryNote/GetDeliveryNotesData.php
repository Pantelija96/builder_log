<?php

namespace App\DTO\DeliveryNote;

use App\DTO\Requests\ListQueryData;
use App\Http\Requests\DeliveryNote\GetDeliveryNotesRequest;
use Carbon\Carbon;

readonly class GetDeliveryNotesData
{
    public function __construct(
        public ListQueryData $list,
        public ?int $supplierId,
        public ?int $dailyLogId,
        public ?string $name,
        public ?Carbon $dateFrom,
        public ?Carbon $dateTo,
    ) {
    }

    public static function fromRequest(GetDeliveryNotesRequest $request,): self {
        return new self(
            list: ListQueryData::fromRequest($request),
            supplierId: $request->integer('supplier_id') ?: null,
            dailyLogId: $request->integer('daily_log_id') ?: null,
            name: $request->string('name') ?: null,
            dateFrom: $request->filled('date_from') ? Carbon::parse($request->date_from) : null,
            dateTo: $request->filled('date_to') ? Carbon::parse($request->date_to) : null,
        );
    }
}
