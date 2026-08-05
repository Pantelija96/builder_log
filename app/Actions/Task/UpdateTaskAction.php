<?php

namespace App\Actions\Task;

use App\Actions\BaseAction;
use App\DTO\Task\UpdateTaskData;
use App\Enums\LogEvent;
use App\Enums\WorkerRole;
use App\Models\Task;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use App\Services\NotificationService;

class UpdateTaskAction extends BaseAction
{
    public function __construct(
        private readonly LoggingService $logging,
        private readonly NotificationService $notifications,
    ) {
    }

    public function execute(
        Task $task,
        UpdateTaskData $data,
        Worker $currentWorker,
        ?string $reason = null,
    ): Task {

        return $this->transaction(function () use ($task, $data, $currentWorker, $reason) {

            $assignmentChanged =
                $task->site_manager_id !== $data->siteManagerId
                || $task->construction_site_id !== $data->constructionSiteId;

            $oldValues = $task->getOriginal();

            $task->update([
                'title' => $data->title,
                'description' => $data->description,
                'due_date' => $data->dueDate,
                'site_manager_id' => $data->siteManagerId,
                'construction_site_id' => $data->constructionSiteId,
            ]);

            $task->refresh();

            if ($assignmentChanged) {
                $this->notifyAssignedWorkers($task);
            }

            $this->logging->activity(
                actor: $currentWorker,
                subject: $task,
                event: LogEvent::TASK_UPDATED
            );

            return $task;
        });
    }

    private function notifyAssignedWorkers(
        Task $task,
    ): void {

        /*
         |----------------------------------------
         | Site manager
         |----------------------------------------
         */

        if ($task->site_manager_id !== null) {

            $this->notifications->taskAssigned(
                worker: $task->siteManager,
                task: $task,
            );

            return;
        }

        /*
         |----------------------------------------
         | Construction site
         |----------------------------------------
         */

        if ($task->construction_site_id !== null) {

            foreach ($task->constructionSite->siteManagers as $manager) {

                $this->notifications->taskAssigned(
                    worker: $manager,
                    task: $task,
                );

            }

            return;
        }

        /*
         |----------------------------------------
         | Global
         |----------------------------------------
         */

        Worker::query()
            ->where('company_id', $task->company_id)
            ->where('role', WorkerRole::SITE_MANAGER)
            ->where('is_active', true)
            ->each(function (Worker $worker) use ($task) {

                $this->notifications->taskAssigned(
                    worker: $worker,
                    task: $task,
                );

            });
    }
}
