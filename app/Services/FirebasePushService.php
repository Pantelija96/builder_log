<?php

namespace App\Services;

use App\Models\WorkerDevice;
use App\Services\Logging\LoggingService;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Throwable;

class FirebasePushService
{
    public function __construct(
        private readonly Messaging $messaging,
        private readonly LoggingService $loggingService,
    ) {}

    public function sendToDevice(
        WorkerDevice $device,
        string $title,
        string $body,
        array $data = [],
    ): bool {
        $message = CloudMessage::new()
            ->withToken($device->fcm_token)
            ->withNotification(
                Notification::create(
                    $title,
                    $body,
                )
            )
            ->withData(
                $this->normalizeData($data)
            );

        try {
            $this->messaging->send($message);

            return true;
        } catch (NotFound $exception) {
            $this->deactivateDevice($device);

            $this->loggingService->error(
                'Firebase push token not found.',
                [
                    'worker_device_id' => $device->id,
                    'worker_id' => $device->worker_id,
                    'exception' => $exception->getMessage(),
                ]
            );

            return false;
        } catch (MessagingException $exception) {
            $this->loggingService->error(
                'Firebase push failed.',
                [
                    'worker_device_id' => $device->id,
                    'worker_id' => $device->worker_id,
                    'exception' => $exception->getMessage(),
                ]
            );

            return false;
        } catch (Throwable $exception) {
            $this->loggingService->error(
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

    private function deactivateDevice(WorkerDevice $device): void
    {
        $device->update([
            'is_active' => false,
        ]);
    }

    private function normalizeData(array $data): array
    {
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
