<?php

namespace App\DTO\Note;

use App\DTO\Requests\ListQueryData;
use App\Http\Requests\Note\GetNotesRequest;

readonly class GetNotesData
{
    public function __construct(
        public ListQueryData $list,

        public ?int $dailyLogId,

        public ?bool $notifyAdmin,

        public ?int $createdBy,
    ) {
    }

    public static function fromRequest(GetNotesRequest $request): self
    {
        return new self(
            list: ListQueryData::fromRequest($request),

            dailyLogId: $request->integer('daily_log_id') ?: null,

            notifyAdmin: $request->has('notify_admin')
                ? $request->boolean('notify_admin')
                : null,

            createdBy: $request->integer('created_by') ?: null,
        );
    }
}
