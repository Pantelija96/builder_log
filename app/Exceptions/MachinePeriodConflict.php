<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class MachinePeriodConflict extends RuntimeException implements ShouldntReport
{
    public const MACHINE = 'MACHINE_TIME_CONFLICT';

    public const WORKER = 'MACHINE_WORKER_TIME_CONFLICT';

    public function __construct(string $message, private readonly string $errorCode)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'errors' => null,
            'error_code' => $this->errorCode,
        ], 409);
    }
}
