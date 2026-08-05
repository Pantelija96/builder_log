<?php

namespace App\Actions\Task;

use App\Actions\BaseAction;
use App\Enums\LogEvent;
use App\Models\Task;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use App\Services\NotificationService;
use App\Services\TaskService;

class DeleteTaskAction extends BaseAction
{
    public function __construct(
        private readonly LoggingService $logging,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function execute(
        Task $task,
        Worker $currentWorker,
        string $reason,
    ): void {

        $this->transaction(function () use (
            $task,
            $currentWorker,
            $reason,
        ) {

            $oldValues = $task->attributesToArray();

            $this->notificationService->deleteTaskNotifications($task);
            $task->delete();

            $this->logging->activity(
                actor: $currentWorker,
                subject: $task,
                event: LogEvent::TASK_DELETED,
            );
        });
    }
}
