<?php

namespace App\DTO\Task;

use App\Http\Requests\Task\UpdateTaskRequest;
use Carbon\Carbon;

readonly class UpdateTaskData
{
    public function __construct(

        public string $title,

        public ?string $description,

        public ?Carbon $dueDate,

        public ?int $siteManagerId,

        public ?int $constructionSiteId,
    ) {
    }

    public static function fromRequest(UpdateTaskRequest $request,): self {

        return new self(

            title: $request->string('title')->toString(),

            description: $request->string('description')->toString() ?: null,

            dueDate: $request->filled('due_date')
                ? Carbon::parse($request->due_date)
                : null,

            siteManagerId: $request->integer('site_manager_id') ?: null,

            constructionSiteId: $request->integer('construction_site_id') ?: null,
        );
    }
}
