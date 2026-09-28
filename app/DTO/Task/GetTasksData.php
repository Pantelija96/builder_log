<?php

namespace App\DTO\Task;

use App\DTO\Requests\ListQueryData;
use App\Http\Requests\Task\GetTasksRequest;
use Carbon\Carbon;
use App\Enums\WorkerRole;

readonly class GetTasksData
{
    public function __construct(
        public ListQueryData $list,
        public ?string $search,
        public ?string $title,
        public ?int $workerId,
        public ?WorkerRole $targetRole,
        public ?int $constructionSiteId,
        public ?int $createdBy,
        public ?bool $completed,
        public ?bool $read,
        public ?Carbon $dueDateFrom,
        public ?Carbon $dueDateTo,
        public ?Carbon $dateCreatedFrom,
        public ?Carbon $dateCreatedTo,
    ) {
    }

    public static function fromRequest(GetTasksRequest $request,): self {

        return new self(
            list: ListQueryData::fromRequest($request),
            search: $request->filled('search') ? $request->string('search')->toString() : null,
            title: $request->filled('title') ? $request->string('title')->toString() : null,
            workerId: $request->integer('worker_id') ?: null,
            targetRole: $request->filled('target_role') ? WorkerRole::from($request->string('target_role')->toString()) : null,
            constructionSiteId: $request->integer('construction_site_id') ?: null,
            createdBy: $request->integer('created_by') ?: null,
            completed: $request->has('completed') ? $request->boolean('completed') : null,
            read: $request->has('read') ? $request->boolean('read') : null,
            dueDateFrom: $request->filled('due_date_from') ? Carbon::parse($request->due_date_from) : null,
            dueDateTo: $request->filled('due_date_to') ? Carbon::parse($request->due_date_to) : null,
            dateCreatedFrom: $request->filled('date_created_from') ? Carbon::parse($request->date_created_from) : null,
            dateCreatedTo: $request->filled('date_created_to') ? Carbon::parse($request->date_created_to) : null,
        );
    }
}
