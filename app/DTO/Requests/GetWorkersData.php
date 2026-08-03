<?php

namespace App\DTO\Requests;

use App\Enums\WorkerRole;
use App\Http\Requests\Get\GetWorkersRequest;

readonly class GetWorkersData
{
    public function __construct(
        public ListQueryData $list,
        public ?int $companyId,
        public ?WorkerRole $role,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $email,
        public ?string $phone,
        public ?bool $isActive,
    ) {
    }

    public static function fromRequest(GetWorkersRequest $request): self
    {
        return new self(
            list: ListQueryData::fromRequest($request),

            companyId: $request->filled('company_id')
                ? $request->integer('company_id')
                : null,

            role: $request->filled('role')
                ? WorkerRole::from($request->string('role')->toString())
                : null,

            firstName: $request->validated('first_name'),
            lastName: $request->validated('last_name'),
            email: $request->validated('email'),
            phone: $request->validated('phone'),

            isActive: $request->filled('is_active')
                ? $request->boolean('is_active')
                : null,
        );
    }
}
