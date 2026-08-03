<?php

namespace App\Services\Authentication;

use App\DTO\Requests\Auth\LoginData;
use App\DTO\Requests\Auth\LoginResponseData;
use App\Exceptions\BusinessException;
use App\Http\Resources\WorkerResource;
use App\Models\Worker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthenticationService
{
    public function login(LoginData $data): LoginResponseData
    {
        $worker = Worker::query()
            ->where('username', $data->username)
            ->first();

        if (!$worker) {
            throw new BusinessException('Invalid credentials.');
        }

        if (!$worker->is_active) {
            throw new BusinessException('Your account is inactive.');
        }

        if (!Hash::check($data->password, $worker->password)) {
            throw new BusinessException('Invalid credentials.');
        }

        $worker->tokens()->delete();
        $token = $worker->createToken('mobile')->plainTextToken;

        return new LoginResponseData(
            worker: $worker,
            token: $token,
        );
    }
}
