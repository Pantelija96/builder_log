<?php

namespace App\DTO\Push;

use App\Http\Requests\Push\RegisterWorkerDeviceRequest;

class RegisterWorkerDeviceData
{
    public function __construct(
        public readonly string $platform,
        public readonly ?string $deviceUuid,
        public readonly string $fcmToken,
        public readonly ?string $deviceName,
        public readonly ?string $appVersion,
    ) {}

    public static function fromRequest(
        RegisterWorkerDeviceRequest $request,
    ): self {
        return new self(
            platform: $request->string('platform')->toString(),

            deviceUuid: $request->filled('device_uuid')
                ? $request->string('device_uuid')->toString()
                : null,

            fcmToken: $request->string('fcm_token')->toString(),

            deviceName: $request->filled('device_name')
                ? $request->string('device_name')->toString()
                : null,

            appVersion: $request->filled('app_version')
                ? $request->string('app_version')->toString()
                : null,
        );
    }
}
