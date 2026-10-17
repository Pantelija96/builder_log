<?php

namespace App\Http\Controllers;

use App\DTO\Push\RegisterWorkerDeviceData;
use App\DTO\Push\UnregisterWorkerDeviceData;
use App\Http\Requests\Push\RegisterWorkerDeviceRequest;
use App\Http\Requests\Push\UnregisterWorkerDeviceRequest;
use App\Models\Worker;
use App\Services\PushNotificationService;
use App\Services\WorkerDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushNotificationController extends ApiController
{
    public function __construct(
        private readonly WorkerDeviceService $workerDeviceService,
        private readonly PushNotificationService $pushNotificationService,
    ) {}

    public function register(
        RegisterWorkerDeviceRequest $request,
    ): JsonResponse {
        /** @var Worker $worker */
        $worker = $request->user();

        $device = $this->workerDeviceService->register(
            worker: $worker,
            data: RegisterWorkerDeviceData::fromRequest($request),
        );

        return $this->success(
            data: [
                'id' => $device->id,
                'platform' => $device->platform,
                'device_uuid' => $device->device_uuid,
                'device_name' => $device->device_name,
                'app_version' => $device->app_version,
                'last_seen_at' => $device->last_seen_at,
                'is_active' => $device->is_active,
            ],
            message: 'Device registered successfully.',
        );
    }

    public function unregister(
        UnregisterWorkerDeviceRequest $request,
    ): JsonResponse {
        /** @var Worker $worker */
        $worker = $request->user();

        $this->workerDeviceService->unregister(
            worker: $worker,
            data: UnregisterWorkerDeviceData::fromRequest($request),
        );

        return $this->success(
            message: 'Device unregistered successfully.',
        );
    }

    public function testSiteManagers(Request $request): JsonResponse
    {
        /** @var Worker $worker */
        $worker = $request->user();

        $result = $this->pushNotificationService
            ->sendTestToSiteManagers($worker);

        return $this->success(
            data: $result,
            message: 'Test push notifications sent successfully.'
        );
    }
}
