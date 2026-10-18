<?php

namespace App\Services;

use App\Models\WorkerDevice;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Throwable;

class FirebasePushService
{
    public function __construct(
        private readonly Messaging $messaging,
    ) {}

    public function sendToDevice(
        WorkerDevice $device,
        string $title,
        string $body,
        array $data = [],
    ): bool {
        $message = CloudMessage::new()
            ->withToken($device->fcm_token)
            ->withData(
                $this->normalizeData([
                    'title' => $title,
                    'body' => $body,
                    ...$data,
                ])
            );

        try {
            $this->messaging->send($message);

            return true;
        } catch (NotFound $exception) {
            $this->deactivateDevice($device);

            Log::error(
                'Firebase push token not found.',
                [
                    'worker_device_id' => $device->id,
                    'worker_id' => $device->worker_id,
                    'exception' => $exception->getMessage(),
                ]
            );

            return false;
        } catch (MessagingException $exception) {
            Log::error(
                'Firebase push failed.',
                [
                    'worker_device_id' => $device->id,
                    'worker_id' => $device->worker_id,
                    'exception' => $exception->getMessage(),
                ]
            );

            return false;
        } catch (Throwable $exception) {
            Log::error(
                'Unexpected Firebase push error.',
                [
                    'worker_device_id' => $device->id,
                    'worker_id' => $device->worker_id,
                    'exception' => $exception->getMessage(),
                ]
            );

            return false;
        }
    }

    private function deactivateDevice(
        WorkerDevice $device,
    ): void {
        $device->update([
            'is_active' => false,
        ]);
    }

    private function normalizeData(
        array $data,
    ): array {
        return collect($data)
            ->mapWithKeys(
                fn ($value, $key) => [
                    (string) $key => is_bool($value)
                        ? ($value ? '1' : '0')
                        : (string) $value,
                ]
            )
            ->all();
    }
}
