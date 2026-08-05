<?php

namespace App\Actions\Task;

use App\Actions\BaseAction;
use App\Enums\LogEvent;
use App\Models\Task;
use App\Models\Worker;
use App\Services\Logging\LoggingService;

class CompleteTaskAction extends BaseAction
{
    public function __construct(
        private readonly LoggingService $logging,
    ) {}

    public function execute(
        Task $task,
        Worker $worker,
    ): Task {

        return $this->transaction(function () use (
            $task,
            $worker,
        ) {

            $task->markAsCompleted($worker);

            $this->logging->activity(
                actor: $worker,
                subject: $task,
                event: LogEvent::TASK_COMPLETED,
            );

            return $task->refresh();
        });
    }
}
