<?php

namespace App\Actions\Task;

//use App\Actions\Attachment\UploadAttachmentsAction;
use App\Actions\BaseAction;
use App\DTO\Task\CreateTaskData;
use App\Enums\LogEvent;
use App\Enums\WorkerRole;
use App\Models\Task;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use App\Services\NotificationService;

class CreateTaskAction extends BaseAction
{
    public function __construct(
        private readonly LoggingService $logging,
        private readonly NotificationService $notifications,
//        private readonly UploadAttachmentsAction $uploadAttachmentsAction,
    )
    {}

    public function execute(
        CreateTaskData $data,
        Worker $currentWorker,
    ): Task
    {

        return $this->transaction(function () use ($data, $currentWorker) {

            $task = Task::create([
                'company_id' => $currentWorker->company_id,

                'site_manager_id' => $data->siteManagerId,

                'construction_site_id' => $data->constructionSiteId,

                'title' => $data->title,

                'description' => $data->description,

                'due_date' => $data->dueDate,

                'created_by' => $currentWorker->id,
            ])->refresh();

//            if (!empty($data->attachments)) {
//
//                $this->uploadAttachmentsAction->execute(
//                    attachable: $task,
//                    files: $data->attachments,
//                    worker: $currentWorker,
//                );
//
//            }

            $this->notifyAssignedWorkers($task);

            $this->logging->activity(
                actor: $currentWorker,
                subject: $task,
                event: LogEvent::TASK_CREATED,
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
