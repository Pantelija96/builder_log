<?php

namespace App\DTO\DailyLog;

use App\Http\Requests\DailyLog\CreateDailyLogRequest;
use Carbon\Carbon;

readonly class CreateDailyLogData
{
    public function __construct(
        public int $companyId,
        public int $constructionSiteId,
        public int $siteManagerId,
        public Carbon $date,
        public array $attachments,
    ) {
    }

    public static function fromRequest(CreateDailyLogRequest $request): self {
        return new self(
            companyId: (int) $request->validated('company_id'),
            constructionSiteId: (int) $request->validated('construction_site_id'),
            siteManagerId: (int) $request->validated('site_manager_id'),
            date: Carbon::parse($request->validated('date')),
            attachments: $request->file('attachments', []),
        );
    }
}
