<?php

namespace App\DTO\Task;

use App\Enums\WorkerRole;
use App\Http\Requests\Task\CreateTaskRequest;
use Carbon\Carbon;

readonly class CreateTaskData
{
    public function __construct(
        public string $title,
        public ?string $description,
        public ?Carbon $dueDate,
        public ?int $workerId,
        public ?WorkerRole $targetRole,
        public ?int $constructionSiteId,
    ) {
    }

    public static function fromRequest(CreateTaskRequest $request): self
    {
        return new self(
            title: $request->string('title')->toString(),
            description: $request->string('description')->toString() ?: null,
            dueDate: $request->filled('due_date') ? Carbon::parse($request->due_date) : null,
            workerId: $request->integer('worker_id') ?: null,
            targetRole: $request->filled('target_role') ? WorkerRole::from($request->string('target_role')->toString()) : null,
            constructionSiteId: $request->integer('construction_site_id') ?: null,
        );
    }
}
