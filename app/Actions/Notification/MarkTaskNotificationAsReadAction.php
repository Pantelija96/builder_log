<?php

namespace App\Actions\Notification;

use App\Models\Notification;
use App\Models\Task;
use App\Models\Worker;

class MarkTaskNotificationAsReadAction
{
    public function execute(Worker $worker, Task $task): void
    {
        Notification::query()
            ->where('worker_id', $worker->id)
            ->where('task_id', $task->id)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);
    }
}
