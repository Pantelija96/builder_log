<?php

namespace App\DTO\Requests\Auth;

use App\Models\Worker;

final readonly class LoginResponseData
{
    public function __construct(
        public Worker $worker,
        public string $token,
    ) {
    }
}
