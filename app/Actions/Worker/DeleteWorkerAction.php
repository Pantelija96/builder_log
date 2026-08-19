<?php

namespace App\Actions\Worker;

use App\Actions\BaseAction;
use App\Enums\LogEvent;
use App\Models\Worker;
use App\Services\Logging\LoggingService;

class DeleteWorkerAction extends BaseAction
{
    public function __construct(
        private readonly LoggingService $logging,
    ) {}

    public function execute(
        Worker $worker,
        Worker $currentWorker,
        string $reason,
    ): void {

        $this->transaction(function () use (
            $worker,
            $currentWorker,
            $reason,
        ) {

            $oldValues = $worker->getAttributes();

            $this->logging->activity(
                actor: $currentWorker,
                subject: $worker,
                event: LogEvent::WORKER_DELETED,
            );

            $this->logging->audit(
                actor: $currentWorker,
                subject: $worker,
                event: LogEvent::WORKER_DELETED,
                oldValues: $oldValues,
                newValues: null,
                reason: $reason,
            );

            $worker->delete();
        });
    }
}
