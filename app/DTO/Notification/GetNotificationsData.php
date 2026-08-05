<?php

namespace App\DTO\Notification;

use App\DTO\Requests\ListQueryData;
use App\Http\Requests\Notification\GetNotificationsRequest;
use Carbon\Carbon;

readonly class GetNotificationsData
{
    public function __construct(
        public ListQueryData $list,
        public ?string $search,
        public ?string $type,
        public ?bool $isRead,
        public ?Carbon $createdFrom,
        public ?Carbon $createdTo,
    ) {
    }

    public static function fromRequest(
        GetNotificationsRequest $request,
    ): self {

        return new self(

            list: ListQueryData::fromRequest($request),

            search: $request->filled('search')
                ? $request->string('search')->toString()
                : null,

            type: $request->filled('type')
                ? $request->string('type')->toString()
                : null,

            isRead: $request->has('is_read')
                ? $request->boolean('is_read')
                : null,

            createdFrom: $request->filled('created_from')
                ? Carbon::parse($request->created_from)
                : null,

            createdTo: $request->filled('created_to')
                ? Carbon::parse($request->created_to)
                : null,
        );
    }
}
