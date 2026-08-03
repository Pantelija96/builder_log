<?php

namespace App\Http\Controllers\Api;

use App\DTO\Requests\Auth\LoginData;
use App\Http\Controllers\ApiController;
use App\Http\Requests\API\LoginRequest;
use App\Http\Resources\LoginResource;
use App\Http\Resources\WorkerResource;
use App\Services\Authentication\AuthenticationService;
use Illuminate\Http\Request;

class AuthController extends ApiController
{
    public function __construct(
        private readonly AuthenticationService $authenticationService
    ) {
    }

    public function login(LoginRequest $request)
    {
        $data = LoginData::fromRequest($request);

        $response = $this->authenticationService->login($data);

        return $this->success(
            new LoginResource($response),
            'Login successful.'
        );
    }

    public function me(Request $request)
    {
        return $this->success(
            data: new WorkerResource(
                $request->user()->load('company')
            )
        );
    }

    public function logout(Request $request)
    {
        $request
            ->user()
            ->currentAccessToken()
            ->delete();

        return $this->success(
            message: 'Logout successful.'
        );
    }

    public function logoutAll(Request $request)
    {
        $request
            ->user()
            ->tokens()
            ->delete();

        return $this->success(
            message: 'Logged out from all devices.'
        );
    }
}
