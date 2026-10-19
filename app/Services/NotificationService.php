<?php

namespace App\Services;

use App\Actions\Notification\CreateNotificationAction;
use App\Actions\Notification\MarkNotificationAsReadAction;
use App\DTO\Notification\GetNotificationsData;
use App\Enums\NotificationType;
use App\Models\DailyLog;
use App\Models\Document;
use App\Models\Excavator;
use App\Models\ExcavatorLog;
use App\Models\MachineAssignment;
use App\Models\Note;
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
        return Notification::query()->whereBelongsTo($worker);
    }

    public function create(
        Worker $worker,
        NotificationType $type,
        string $title,
        string $message,
        ?array $payload = null,
        ?Task $task = null,
    ): Notification {

        $notification = $this->createNotificationAction->execute(
            worker: $worker,
            type: $type,
            title: $title,
            message: $message,
            payload: $payload,
            task: $task,
        );

        if ($type !== NotificationType::TASK) {
            $notification->markAsRead();
            $notification->refresh();
        }

        return $notification;
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
        return Notification::query()
            ->where('worker_id', $worker->id)
            ->where('type', NotificationType::TASK)
            ->whereNotNull('task_id')
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

    public function taskAssigned(Worker $worker, Task $task): Notification {
        return $this->create(
            worker: $worker,
            type: NotificationType::TASK,
            title: 'Admin je zadao novi zadatak',
            message: $task->title,
            payload: [
                'task_id' => $task->id,
            ],
            task: $task,
        );
    }

    public function taskCompleted(Worker $worker, Task $task, Worker $completedBy): Notification
    {
        return $this->create(
            worker: $worker,
            type: NotificationType::TASK,
            title: 'Zavrsen zadatak',
            message: sprintf(
                '%s %s je zavrsio: "%s".',
                $completedBy->first_name,
                $completedBy->last_name,
                $task->title,
            ),
            payload: [
                'task_id' => $task->id,
                'completed_by' => $completedBy->id,
            ],
            task: $task,
        );
    }

    public function taskReopened(Worker $worker, Task $task, Worker $reopenedBy): Notification
    {
        return $this->create(
            worker: $worker,
            type: NotificationType::TASK,
            title: 'Zadatak ponovo otvoren',
            message: sprintf(
                '%s %s je ponovo otvorio zadatak: "%s".',
                $reopenedBy->first_name,
                $reopenedBy->last_name,
                $task->title,
            ),
            payload: [
                'task_id' => $task->id,
                'reopened_by' => $reopenedBy->id,
            ],
            task: $task,
        );
    }

    public function deleteTaskNotifications(Task $task): void
    {
        Notification::query()
            ->where('type', NotificationType::TASK)
            ->where('task_id', $task->id)
            ->forceDelete();
    }

    public function markTaskNotificationAsRead(
        Worker $worker,
        Task $task,
    ): void {
        Notification::query()
            ->where('worker_id', $worker->id)
            ->where('task_id', $task->id)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);
    }

    public function documentCreated(
        Worker $worker,
        Document $document,
    ): Notification {
        return $this->create(
            worker: $worker,
            type: NotificationType::DOCUMENT,
            title: 'Admin dodao novi dokument',
            message: $document->name,
            payload: [
                'document_id' => $document->id,
                'construction_site_id' => $document->construction_site_id,
            ],
        );
    }

    public function machineAssigned(
        Worker $worker,
        MachineAssignment $assignment,
        Worker $assignedBy,
        string $machineType,
    ): Notification {
        $machineName = $this->machineTypeMapping($machineType);

        return $this->create(
            worker: $worker,
            type: NotificationType::MACHINE_ASSIGNMENT,
            title: $machineName . ' ti je dodeljen',
            message: sprintf(
                '%s %s ti je dodelio %s.',
                $assignedBy->first_name,
                $assignedBy->last_name,
                strtolower($machineName),
            ),
            payload: [
                'machine_assignment_id' => $assignment->id,
                'machine_id' => $assignment->machine_id,
                'construction_site_id' => $assignment->construction_site_id,
                'machine_type' => $machineType,
                'assigned_by' => $assignedBy->id,
            ],
        );
    }

    public function workerStartedDay(
        Worker $worker,
        Worker $startedBy,
        int $machineAssignmentId,
        string $logType,
        int $logId,
    ): Notification {
        return $this->create(
            worker: $worker,
            type: NotificationType::ATTENDANCE,
            title: 'Radnik zapoceo dan',
            message: sprintf(
                '%s %s je zapoceo radni dan.',
                $startedBy->first_name,
                $startedBy->last_name,
            ),
            payload: [
                'worker_id' => $startedBy->id,
                'machine_assignment_id' => $machineAssignmentId,
                'log_type' => $logType,
                'log_id' => $logId,
            ],
        );
    }

    public function workerFinishedDay(
        Worker $worker,
        Worker $finishedBy,
        int $machineAssignmentId,
        string $logType,
        int $logId,
    ): Notification {
        return $this->create(
            worker: $worker,
            type: NotificationType::ATTENDANCE,
            title: 'Radnik zavrsio dan',
            message: sprintf(
                '%s %s je zavrsio radni dan.',
                $finishedBy->first_name,
                $finishedBy->last_name,
            ),
            payload: [
                'worker_id' => $finishedBy->id,
                'machine_assignment_id' => $machineAssignmentId,
                'log_type' => $logType,
                'log_id' => $logId,
            ],
        );
    }

    public function noteCreated(
        Worker $worker,
        Note $note,
        Worker $createdBy,
    ): Notification {
        return $this->create(
            worker: $worker,
            type: NotificationType::NOTE,
            title: 'Kreiran novi gradjevinski izvestaj',
            message: sprintf(
                '%s %s je kreirao novi gradjevinski izvestaj',
                $createdBy->first_name,
                $createdBy->last_name,
            ),
            payload: [
                'note_id' => $note->id,
                'daily_log_id' => $note->daily_log_id,
                'construction_site_id' => $note->construction_site_id,
                'created_by' => $createdBy->id,
            ],
        );
    }

    private function machineTypeMapping(string $type): string
    {
        return match (strtolower($type)) {
            'excavator' => 'Bager',
            'truck' => 'Kamion',
            default => $type,
        };
    }
}
