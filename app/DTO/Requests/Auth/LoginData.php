<?php

namespace App\DTO\Requests\Auth;

use App\Http\Requests\API\LoginRequest;

final readonly class LoginData
{
    public function __construct(
        public string $username,
        public string $password,
    ) {
    }

    public static function fromRequest(LoginRequest $request): self
    {
        return new self(
            username: $request->string('username')->toString(),
            password: $request->string('password')->toString(),
        );
    }
}
