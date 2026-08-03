<?php

namespace App\DTO\SubcontractorLog;

use App\DTO\Requests\ListQueryData;
use App\Http\Requests\SubcontractorLog\GetSubcontractorLogsRequest;

readonly class GetSubcontractorLogsData
{
    public function __construct(
        public ListQueryData $list,
        public ?int $subcontractorId,
    ) {
    }

    public static function fromRequest(GetSubcontractorLogsRequest $request,): self {
        return new self(
            list: ListQueryData::fromRequest($request),
            subcontractorId: $request->integer('subcontractor_id') ?: null,
        );
    }
}
