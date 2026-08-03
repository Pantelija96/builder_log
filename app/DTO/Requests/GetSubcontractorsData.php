<?php

namespace App\DTO\Requests;

use App\Http\Requests\Get\GetSubcontractorsRequest;

readonly class GetSubcontractorsData
{
    public function __construct(
        public ListQueryData $list,
        public ?bool $isActive,
        public ?int $dailyLogId,
    ) {
    }

    public static function fromRequest(GetSubcontractorsRequest $request): self
    {
        return new self(
            list: ListQueryData::fromRequest($request),
            isActive: $request->has('is_active') ? $request->boolean('is_active') : null,
            dailyLogId: $request->integer('daily_log_id') ?: null,
        );
    }
}
