<?php

namespace App\DTO\Worker;

use App\Http\Requests\Worker\UpdateWorkerRequest;
use App\Enums\WorkerRole;

readonly class UpdateWorkerData
{
    public function __construct(
        public ?string $firstName,
        public ?string $lastName,
        public ?string $phone,
        public ?WorkerRole $role,
        public ?string $username,
        public ?string $password,
        public ?string $email,
        public ?bool $isActive,
        public array $providedFields,
    ) {}

    public static function fromRequest(UpdateWorkerRequest $request,): self
    {
        return new self(
            firstName: $request->has('first_name') ? $request->string('first_name')->toString() : null,
            lastName: $request->has('last_name') ? $request->string('last_name')->toString() : null,
            phone: $request->has('phone') ? $request->input('phone') : null,
            role: $request->has('role') ? WorkerRole::from($request->string('role')->toString()) : null,
            username: $request->has('username') ? $request->string('username')->toString() : null,
            password: $request->has('password') ? $request->string('password')->toString() : null,
            email: $request->has('email') ? $request->input('email') : null,
            isActive: $request->has('is_active') ? $request->boolean('is_active') : null,
            providedFields: $request->keys(),
        );
    }
}
