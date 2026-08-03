<?php

namespace App\DTO\DeliveryNote;

use App\DTO\Requests\ListQueryData;
use App\Http\Requests\DeliveryNote\GetDeliveryNotesRequest;

readonly class GetDeliveryNotesData
{
    public function __construct(
        public ListQueryData $list,
        public ?int $supplierId,
        public ?int $dailyLogId,
        public ?string $name
    ) {
    }

    public static function fromRequest(GetDeliveryNotesRequest $request,): self {
        return new self(
            list: ListQueryData::fromRequest($request),
            supplierId: $request->integer('supplier_id') ?: null,
            dailyLogId: $request->integer('daily_log_id') ?: null,
            name: $request->string('name') ?: null,
        );
    }
}
