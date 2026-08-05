<?php

namespace App\DTO\Task;

use App\DTO\Requests\ListQueryData;
use App\Http\Requests\Task\GetTasksRequest;
use Carbon\Carbon;

readonly class GetTasksData
{
    public function __construct(
        public ListQueryData $list,

        public ?string $search,

        public ?string $title,

        public ?int $siteManagerId,

        public ?int $constructionSiteId,

        public ?int $createdBy,

        public ?bool $completed,

        public ?bool $read,

        public ?Carbon $dueDateFrom,

        public ?Carbon $dueDateTo,
    ) {
    }

    public static function fromRequest(GetTasksRequest $request,): self {

        return new self(

            list: ListQueryData::fromRequest($request),

            search: $request->filled('search')
                ? $request->string('search')->toString()
                : null,

            title: $request->filled('title')
                ? $request->string('title')->toString()
                : null,

            siteManagerId: $request->integer('site_manager_id') ?: null,

            constructionSiteId: $request->integer('construction_site_id') ?: null,

            createdBy: $request->integer('created_by') ?: null,

            completed: $request->has('completed')
                ? $request->boolean('completed')
                : null,

            read: $request->has('read')
                ? $request->boolean('read')
                : null,

            dueDateFrom: $request->filled('due_date_from')
                ? Carbon::parse($request->due_date_from)
                : null,

            dueDateTo: $request->filled('due_date_to')
                ? Carbon::parse($request->due_date_to)
                : null,
        );
    }
}
