<?php

namespace App\Actions\Task;

use App\Actions\BaseAction;
use App\DTO\Task\CreateTaskData;
use App\Enums\LogEvent;
use App\Models\Task;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use App\Services\NotificationService;
use App\Enums\WorkerRole;
use App\Exceptions\BusinessException;

class CreateTaskAction extends BaseAction
{
    public function __construct(
        private readonly LoggingService $logging,
        private readonly NotificationService $notifications,
    ) {
    }

    public function execute(
        CreateTaskData $data,
        Worker $currentWorker,
    ): Task {
        $this->ensureValidTargetWorker(
            workerId: $data->workerId,
            currentWorker: $currentWorker,
        );

        return $this->transaction(function () use ($data, $currentWorker) {

            $task = Task::create([
                'company_id' => $currentWorker->company_id,
                'worker_id' => $data->workerId,
                'target_role' => $data->targetRole,
                'construction_site_id' => $data->constructionSiteId,
                'title' => $data->title,
                'description' => $data->description,
                'due_date' => $data->dueDate,
                'created_by' => $currentWorker->id,
            ])->refresh();

            $this->notifyAssignedWorkers($task);

            $this->logging->activity(
                actor: $currentWorker,
                subject: $task,
                event: LogEvent::TASK_CREATED,
            );

            return $task;
        });
    }

    private function notifyAssignedWorkers(Task $task): void
    {
        // One specific worker
        if ($task->worker_id !== null) {
            $this->notifications->taskAssigned(
                worker: $task->worker,
                task: $task,
            );

            return;
        }

        // Construction site -> site managers assigned to that site
        if ($task->construction_site_id !== null) {
            foreach ($task->constructionSite->siteManagers as $manager) {
                $this->notifications->taskAssigned(
                    worker: $manager,
                    task: $task,
                );
            }

            return;
        }

        // All workers with selected role
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
