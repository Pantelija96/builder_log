<?php

namespace App\Actions\Notification;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\Task;

class DeleteTaskNotificationsAction
{
    public function execute(Task $task): void
    {
        Notification::query()
            ->where('type', NotificationType::TASK)
            ->where('task_id', $task->id)
            ->forceDelete();
    }
}
