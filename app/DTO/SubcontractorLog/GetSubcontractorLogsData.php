<?php

namespace App\DTO\SubcontractorLog;

use App\DTO\Requests\ListQueryData;
use App\Http\Requests\SubcontractorLog\GetSubcontractorLogsRequest;

readonly class GetSubcontractorLogsData
{
    public function __construct(
        public ListQueryData $list,
        public ?int $dailyLogId,
        public ?int $subcontractorId,
        public ?string $dateFrom,
        public ?string $dateTo,
    ) {
    }

    public static function fromRequest(
        GetSubcontractorLogsRequest $request,
    ): self {
        return new self(
            list: ListQueryData::fromRequest($request),
            dailyLogId: $request->integer('daily_log_id') ?: null,
            subcontractorId: $request->integer('subcontractor_id') ?: null,
            dateFrom: $request->filled('date_from')
                ? $request->date('date_from')->toDateString()
                : null,
            dateTo: $request->filled('date_to')
                ? $request->date('date_to')->toDateString()
                : null,
        );
    }
}
