<?php

namespace App\Actions\Notification;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\Task;

class MarkAllTaskNotificationsAsReadAction
{
    public function execute(Task $task): void
    {
        Notification::query()
            ->where('type', NotificationType::TASK)
            ->where('task_id', $task->id)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);
    }
}
