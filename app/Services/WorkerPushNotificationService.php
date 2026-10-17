<?php

namespace App\Services;

use App\Models\Worker;

class WorkerPushNotificationService
{
    public function __construct(
        private readonly FirebasePushService $firebasePushService,
    ) {}

    public function send(
        Worker $worker,
        string $title,
        string $body,
        array $data = [],
    ): void {
        $worker->activeDevices()
            ->get()
            ->each(function ($device) use ($title, $body, $data) {
                $this->firebasePushService->sendToDevice(
                    device: $device,
                    title: $title,
                    body: $body,
                    data: $data,
                );
            });
    }
}
