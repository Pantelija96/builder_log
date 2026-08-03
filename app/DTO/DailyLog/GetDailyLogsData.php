<?php

namespace App\DTO\DailyLog;

use App\DTO\Requests\ListQueryData;
use App\Http\Requests\DailyLog\GetDailyLogsRequest;

readonly class GetDailyLogsData
{
    public function __construct(
        public ListQueryData $list,
        public ?int $companyId,
        public ?int $constructionSiteId,
        public ?int $siteManagerId,
        public ?string $date,
        public ?bool $isLocked,
    ) {
    }

    public static function fromRequest(GetDailyLogsRequest $request): self
    {
        return new self(
            list: ListQueryData::fromRequest($request),

            companyId: $request->filled('company_id')
                ? $request->integer('company_id')
                : null,

            constructionSiteId: $request->filled('construction_site_id')
                ? $request->integer('construction_site_id')
                : null,

            siteManagerId: $request->filled('site_manager_id')
                ? $request->integer('site_manager_id')
                : null,

            date: $request->validated('date'),

            isLocked: $request->filled('is_locked')
                ? $request->boolean('is_locked')
                : null,
        );
    }
}
