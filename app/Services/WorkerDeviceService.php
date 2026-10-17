<?php

namespace App\Services;

use App\Actions\Push\RegisterWorkerDeviceAction;
use App\Actions\Push\UnregisterWorkerDeviceAction;
use App\DTO\Push\RegisterWorkerDeviceData;
use App\DTO\Push\UnregisterWorkerDeviceData;
use App\Models\Worker;
use App\Models\WorkerDevice;

class WorkerDeviceService
{
    public function __construct(
        private readonly RegisterWorkerDeviceAction $registerWorkerDeviceAction,
        private readonly UnregisterWorkerDeviceAction $unregisterWorkerDeviceAction,
    ) {}

    public function register(
        Worker $worker,
        RegisterWorkerDeviceData $data,
    ): WorkerDevice {
        return $this->registerWorkerDeviceAction->execute(
            worker: $worker,
            data: $data,
        );
    }

    public function unregister(
        Worker $worker,
        UnregisterWorkerDeviceData $data,
    ): void {
        $this->unregisterWorkerDeviceAction->execute(
            worker: $worker,
            data: $data,
        );
    }
}
