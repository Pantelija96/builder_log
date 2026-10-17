<?php

namespace App\DTO\Push;

use App\Http\Requests\Push\UnregisterWorkerDeviceRequest;

class UnregisterWorkerDeviceData
{
    public function __construct(
        public readonly string $fcmToken,
    ) {}

    public static function fromRequest(
        UnregisterWorkerDeviceRequest $request,
    ): self {
        return new self(
            fcmToken: $request->string('fcm_token')->toString(),
        );
    }
}
