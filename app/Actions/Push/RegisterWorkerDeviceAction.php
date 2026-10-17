<?php

namespace App\Actions\Push;

use App\Actions\BaseAction;
use App\DTO\Push\RegisterWorkerDeviceData;
use App\Models\Worker;
use App\Models\WorkerDevice;

class RegisterWorkerDeviceAction extends BaseAction
{
    public function execute(
        Worker $worker,
        RegisterWorkerDeviceData $data,
    ): WorkerDevice {
        return $this->transaction(function () use ($worker, $data) {

            /*
             * Ako imamo device_uuid, prvo pokušavamo da pronađemo
             * postojeću instalaciju ovog korisnika.
             *
             * Ovo je važno zato što FCM token može da se promeni.
             */
            $device = null;

            if ($data->deviceUuid !== null) {
                $device = WorkerDevice::query()
                    ->where('worker_id', $worker->id)
                    ->where('device_uuid', $data->deviceUuid)
                    ->first();
            }

            /*
             * Ako uređaj nije pronađen preko device_uuid-a,
             * pokušavamo preko samog FCM tokena.
             *
             * fcm_token je UNIQUE u bazi.
             */
            if ($device === null) {
                $device = WorkerDevice::query()
                    ->where('fcm_token', $data->fcmToken)
                    ->first();
            }

            /*
             * Postojeći uređaj:
             *
             * - osvežavamo FCM token
             * - osvežavamo ownership
             * - aktiviramo ga
             * - beležimo poslednji kontakt
             */
            if ($device !== null) {
                $device->update([
                    'company_id' => $worker->company_id,
                    'worker_id' => $worker->id,
                    'platform' => $data->platform,
                    'device_uuid' => $data->deviceUuid,
                    'fcm_token' => $data->fcmToken,
                    'device_name' => $data->deviceName,
                    'app_version' => $data->appVersion,
                    'last_seen_at' => now(),
                    'is_active' => true,
                ]);

                return $device->refresh();
            }

            /*
             * Prva registracija ovog uređaja.
             */
            return WorkerDevice::create([
                'company_id' => $worker->company_id,
                'worker_id' => $worker->id,
                'platform' => $data->platform,
                'device_uuid' => $data->deviceUuid,
                'fcm_token' => $data->fcmToken,
                'device_name' => $data->deviceName,
                'app_version' => $data->appVersion,
                'last_seen_at' => now(),
                'is_active' => true,
            ]);
        });
    }
}
