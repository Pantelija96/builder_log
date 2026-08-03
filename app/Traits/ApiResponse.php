<?php

namespace App\Traits;

use \Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function error(string $message, mixed $errors = null, int $status = 400): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    protected function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function noContent(): Response
    {
        return response()->noContent();
    }

    protected function accepted(?string $message = null): JsonResponse
    {
        return $this->success(null, $message, 202);
    }

}
