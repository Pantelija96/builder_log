<?php

namespace App\Actions\Task;

use App\Actions\BaseAction;
use App\Enums\LogEvent;
use App\Enums\WorkerRole;
use App\Models\Task;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use App\Services\NotificationService;
use App\Services\WorkerPushNotificationService;

class CompleteTaskAction extends BaseAction
{
    public function __construct(
        private readonly LoggingService $logging,
        private readonly NotificationService $notifications,
        private readonly WorkerPushNotificationService $pushNotifications,
    ) {
    }

    public function execute(
        Task $task,
        Worker $worker,
    ): Task {

        return $this->transaction(function () use (
            $task,
            $worker,
        ) {

            $task->markAsCompleted($worker);

            $this->notifyAdmins(
                task: $task,
                completedBy: $worker,
            );

            $this->logging->activity(
                actor: $worker,
                subject: $task,
                event: LogEvent::TASK_COMPLETED,
            );

            return $task->refresh();
        });
    }

    private function notifyAdmins(
        Task $task,
        Worker $completedBy,
    ): void {
        Worker::query()
            ->where('company_id', $task->company_id)
            ->where('role', WorkerRole::ADMIN)
            ->where('is_active', true)
            ->each(function (Worker $admin) use ($task, $completedBy) {
                $notification = $this->notifications->taskCompleted(
                    worker: $admin,
                    task: $task,
                    completedBy: $completedBy,
                );

                $this->pushNotifications->send(
                    worker: $admin,
                    title: $notification->title,
                    body: $notification->message,
                    data: [
                        'type' => 'task_completed',
                        'task_id' => $task->id,
                        'completed_by' => $completedBy->id,
                        'notification_id' => $notification->id,
                    ],
                );
            });
    }
}
