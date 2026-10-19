<?php

namespace App\Actions\Notification;

use App\Actions\BaseAction;
use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\Task;
use App\Models\Worker;

class CreateNotificationAction extends BaseAction
{
    public function execute(
        Worker $worker,
        NotificationType $type,
        string $title,
        string $message,
        ?array $payload = null,
        ?Task $task = null,
    ): Notification {

        return Notification::create([
            'company_id' => $worker->company_id,
            'worker_id' => $worker->id,
            'task_id' => $task?->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'payload' => $payload,
        ]);
    }
}
