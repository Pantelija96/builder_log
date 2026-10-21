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
        $taskType = $this->getTaskType(
            task: $task,
        );

        Worker::query()
            ->where('company_id', $task->company_id)
            ->where('role', WorkerRole::ADMIN)
            ->where('is_active', true)
            ->each(function (Worker $admin) use (
                $task,
                $completedBy,
                $taskType,
            ) {
                $notification = $this->notifications->taskCompleted(
                    worker: $admin,
                    task: $task,
                    completedBy: $completedBy,
                );

                $data = [
                    'type' => "task_completed",
                    'task_notification_type' => $taskType,
                    'task_id' => $task->id,
                    'completed_by' => $completedBy->id,
                    'notification_id' => $notification->id,
                ];

                if (in_array($taskType, [
                    'manager',
                    'excavator_driver',
                    'truck_driver',
                ], true)) {
                    $data['worker_id'] = $completedBy->id;
                }

                if ($taskType === 'construction_site') {
                    $data['construction_site_id'] =
                        $task->construction_site_id;
                }

                $this->pushNotifications->send(
                    worker: $admin,
                    title: $notification->title,
                    body: $notification->message,
                    data: $data,
                );
            });
    }

    private function getTaskType(
        Task $task,
    ): string {
        if ($task->construction_site_id !== null) {
            return 'construction_site';
        }

        if ($task->target_role === WorkerRole::SITE_MANAGER && $task->worker_id === null) {
            return 'general';
        }

        if ($task->target_role !== null) {
            return match ($task->target_role) {
                WorkerRole::SITE_MANAGER => 'manager',
                WorkerRole::OPERATOR => 'excavator_driver',
                WorkerRole::DRIVER => 'truck_driver',
                default => 'general',
            };
        }

        if ($task->worker_id !== null) {
            $task->loadMissing('worker');

            return match ($task->worker?->role) {
                WorkerRole::SITE_MANAGER => 'manager',
                WorkerRole::OPERATOR => 'excavator_driver',
                WorkerRole::DRIVER => 'truck_driver',
                default => 'general',
            };
        }

        return 'general';
    }
}
