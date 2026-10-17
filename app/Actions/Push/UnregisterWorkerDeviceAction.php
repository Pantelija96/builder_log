<?php

namespace App\Actions\Push;

use App\Actions\BaseAction;
use App\DTO\Push\UnregisterWorkerDeviceData;
use App\Models\Worker;
use App\Models\WorkerDevice;

class UnregisterWorkerDeviceAction extends BaseAction
{
    public function execute(
        Worker $worker,
        UnregisterWorkerDeviceData $data,
    ): void {
        $this->transaction(function () use ($worker, $data) {
            WorkerDevice::query()
                ->where('worker_id', $worker->id)
                ->where('fcm_token', $data->fcmToken)
                ->update([
                    'is_active' => false,
                    'last_seen_at' => now(),
                ]);
        });
    }
}
