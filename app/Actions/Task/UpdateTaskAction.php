<?php

namespace App\Actions\Task;

use App\Actions\BaseAction;
use App\DTO\Task\UpdateTaskData;
use App\Enums\LogEvent;
use App\Models\Task;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use App\Services\NotificationService;
use App\Enums\WorkerRole;
use App\Exceptions\BusinessException;

class UpdateTaskAction extends BaseAction
{
    public function __construct(
        private readonly LoggingService $logging,
        private readonly NotificationService $notifications,
    ) {
    }

    public function execute(Task $task, UpdateTaskData $data, Worker $currentWorker, ?string $reason = null,): Task {
        $this->ensureValidTargetWorker(
            workerId: $data->workerId,
            currentWorker: $currentWorker,
        );

        return $this->transaction(function () use ($task, $data, $currentWorker, $reason) {
            $assignmentChanged =
                $task->worker_id !== $data->workerId
                || $task->target_role !== $data->targetRole
                || $task->construction_site_id !== $data->constructionSiteId;

            $oldValues = $task->getOriginal();

            $task->update([
                'title' => $data->title,
                'description' => $data->description,
                'due_date' => $data->dueDate,
                'worker_id' => $data->workerId,
                'target_role' => $data->targetRole,
                'construction_site_id' => $data->constructionSiteId,
            ]);

            $task->refresh();

            if ($assignmentChanged) {
                $this->notifyAssignedWorkers($task);
            }

            $this->logging->activity(
                actor: $currentWorker,
                subject: $task,
                event: LogEvent::TASK_UPDATED,
            );

            return $task;
        });
    }

    private function notifyAssignedWorkers(Task $task): void
    {
        if ($task->worker_id !== null) {
            $this->notifications->taskAssigned(
                worker: $task->worker,
                task: $task,
            );

            return;
        }

        if ($task->construction_site_id !== null) {
            foreach ($task->constructionSite->siteManagers as $manager) {
                $this->notifications->taskAssigned(
                    worker: $manager,
                    task: $task,
                );
            }

            return;
        }

        if ($task->target_role !== null) {
            Worker::query()
                ->where('company_id', $task->company_id)
                ->where('role', $task->target_role)
                ->where('is_active', true)
                ->each(function (Worker $worker) use ($task) {
                    $this->notifications->taskAssigned(
                        worker: $worker,
                        task: $task,
                    );
                });
        }
    }

    private function ensureValidTargetWorker(
        ?int $workerId,
        Worker $currentWorker,
    ): void {
        if ($workerId === null) {
            return;
        }

        $worker = Worker::query()
            ->whereKey($workerId)
            ->where('company_id', $currentWorker->company_id)
            ->where('is_active', true)
            ->first();

        if ($worker === null) {
            throw new BusinessException(
                'Selected worker does not exist or is inactive.'
            );
        }

        if (! in_array($worker->role, [
            WorkerRole::SITE_MANAGER,
            WorkerRole::OPERATOR,
            WorkerRole::DRIVER,
        ], true)) {
            throw new BusinessException(
                'Tasks can only be assigned to site managers, operators, or drivers.'
            );
        }
    }
}
