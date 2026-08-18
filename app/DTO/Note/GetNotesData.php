<?php

namespace App\DTO\Note;

use App\DTO\Requests\ListQueryData;
use App\Http\Requests\Note\GetNotesRequest;
use Carbon\Carbon;

readonly class GetNotesData
{
    public function __construct(
        public ListQueryData $list,
        public ?int $dailyLogId,
        public ?int $constructionSiteId,
        public ?int $siteManagerId,
        public ?bool $notifyAdmin,
        public ?int $createdBy,
        public ?Carbon $dateCreatedFrom,
        public ?Carbon $dateCreatedTo,
    ) {
    }

    public static function fromRequest(GetNotesRequest $request): self
    {
        return new self(
            list: ListQueryData::fromRequest($request),
            dailyLogId: $request->integer('daily_log_id') ?: null,
            constructionSiteId: $request->integer('construction_site_id') ?: null,
            siteManagerId: $request->integer('site_manager_id') ?: null,
            notifyAdmin: $request->has('notify_admin') ? $request->boolean('notify_admin') : null,
            createdBy: $request->integer('created_by') ?: null,
            dateCreatedFrom: $request->filled('date_created_from') ? Carbon::parse($request->input('date_created_from')) : null,
            dateCreatedTo: $request->filled('date_created_to') ? Carbon::parse($request->input('date_created_to')) : null,
        );
    }
}
