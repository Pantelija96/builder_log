<?php

namespace App\DTO\Worker;

use App\Enums\WorkerRole;
use App\Http\Requests\Worker\CreateWorkerRequest;

readonly class CreateWorkerData
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $phone,
        public WorkerRole $role,
        public string $username,
        public string $password,
        public ?string $email,
        public bool $isActive,
    ) {}

    public static function fromRequest(
        CreateWorkerRequest $request,
    ): self {
        return new self(
            firstName: $request->string('first_name')->toString(),
            lastName: $request->string('last_name')->toString(),
            phone: $request->filled('phone') ? $request->string('phone')->toString() : null,
            role: WorkerRole::from($request->string('role')->toString()),
            username: $request->string('username')->toString(),
            password: $request->string('password')->toString(),
            email: $request->filled('email') ? $request->string('email')->toString() : null,
            isActive: $request->boolean('is_active', true),
        );
    }
}
