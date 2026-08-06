<?php

namespace App\Actions\Task;

use App\Actions\BaseAction;
use App\Models\Task;
use App\Models\Worker;
use App\Services\NotificationService;

class MarkTaskAsReadAction extends BaseAction
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
    }

    public function execute(Task $task, Worker $worker,): Task
    {
        return $this->transaction(function () use ($task, $worker) {
            $task->markAsRead();
            $this->notificationService->markTaskNotificationAsRead(
                worker: $worker,
                task: $task,
            );
            return $task->refresh();
        });
    }
}
