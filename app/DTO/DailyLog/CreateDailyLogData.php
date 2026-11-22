<?php

namespace App\DTO\DailyLog;

use App\Http\Requests\DailyLog\CreateDailyLogRequest;
use Carbon\Carbon;

readonly class CreateDailyLogData
{
    public function __construct(
        public int $constructionSiteId,
        public Carbon $date,
        public array $attachments,
    ) {}

    public static function fromRequest(CreateDailyLogRequest $request): self
    {
        return new self(
            constructionSiteId: (int) $request->validated('construction_site_id'),
            date: Carbon::parse($request->validated('date')),
            attachments: $request->file('attachments', []),
        );
    }
}
