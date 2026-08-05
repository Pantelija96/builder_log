<?php

namespace App\Services;

use App\Actions\Notification\CreateNotificationAction;
use App\Actions\Notification\MarkNotificationAsReadAction;
use App\DTO\Notification\GetNotificationsData;
use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\Task;
use App\Models\Worker;
use App\QueryFilters\NotificationFilter;
use Illuminate\Database\Eloquent\Collection;

class NotificationService
{
    public function __construct(
        private readonly CreateNotificationAction $createNotificationAction,
        private readonly MarkNotificationAsReadAction $markNotificationAsReadAction,
    ) {
    }

    private function query(Worker $worker)
    {
        return Notification::query()
            ->whereBelongsTo($worker);
    }

    public function create(
        Worker $worker,
        NotificationType $type,
        string $title,
        string $message,
        ?array $payload = null,
    ): Notification {

        return $this->createNotificationAction->execute(
            worker: $worker,
            type: $type,
            title: $title,
            message: $message,
            payload: $payload,
        );
    }

    public function get(
        Worker $worker,
        GetNotificationsData $data,
    ): Collection {

        return (new NotificationFilter($data))
            ->apply(
                $this->query($worker)
            )
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }

    public function unreadCount(
        Worker $worker,
    ): int {

        return $this->query($worker)
            ->whereNull('read_at')
            ->count();
    }

    public function markAsRead(
        Worker $worker,
        Notification $notification,
    ): Notification {

        abort_unless(
            $notification->worker_id === $worker->id,
            403
        );

        return $this->markNotificationAsReadAction
            ->execute($notification);
    }

    public function taskAssigned(Worker $worker, Task $task,): Notification {
        return $this->create(
            worker: $worker,
            type: NotificationType::TASK,
            title: 'New task assigned',
            message: $task->title,
            payload: [
                'task_id' => $task->id,
            ],
        );
    }
}
