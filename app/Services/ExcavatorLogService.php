<?php

namespace App\Services;

use App\Actions\ExcavatorLog\CreateExcavatorLogAction;
use App\Actions\ExcavatorLog\DeleteExcavatorLogAction;
use App\Actions\ExcavatorLog\GetAvailableExcavatorsAction;
use App\Actions\ExcavatorLog\GetOccupiedExcavatorsAction;
use App\Actions\ExcavatorLog\UpdateExcavatorLogAction;
use App\DTO\ExcavatorLog\CreateExcavatorLogData;
use App\DTO\ExcavatorLog\CreateExcavatorLogForOperatorData;
use App\DTO\ExcavatorLog\UpdateExcavatorLogData;
use App\Models\DailyLog;
use App\Models\ExcavatorLog;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Collection;

class ExcavatorLogService
{
    public function __construct(
        private readonly CreateExcavatorLogAction $createExcavatorLogAction,
        private readonly UpdateExcavatorLogAction $updateExcavatorLogAction,
        private readonly DeleteExcavatorLogAction $deleteExcavatorLogAction,
        private readonly GetAvailableExcavatorsAction $getAvailableExcavatorsAction,
        private readonly GetOccupiedExcavatorsAction $getOccupiedExcavatorsAction,
    ) {}

    public function create(DailyLog $dailyLog, CreateExcavatorLogData $data, Worker $currentWorker,): ExcavatorLog
    {
        return $this->createExcavatorLogAction->execute(
            dailyLog: $dailyLog,
            data: $data,
            currentWorker: $currentWorker,
        );
    }

    public function createForOperator(CreateExcavatorLogForOperatorData $data, Worker $currentWorker,): ExcavatorLog
    {
        return $this->createExcavatorLogAction->executeForOperator(
            data: $data,
            currentWorker: $currentWorker,
        );
    }

    public function getAvailable(Worker $currentWorker,): Collection
    {
        return $this->getAvailableExcavatorsAction->execute(
            currentWorker: $currentWorker,
        );
    }

    public function getOccupied(Worker $currentWorker,): Collection
    {
        return $this->getOccupiedExcavatorsAction->execute(
            currentWorker: $currentWorker,
        );
    }

    public function update(ExcavatorLog $excavatorLog, UpdateExcavatorLogData $data, Worker $currentWorker, ?string $reason = null,): ExcavatorLog
    {
        $this->ensureCompanyAccess(
            excavatorLog: $excavatorLog,
            currentWorker: $currentWorker,
        );

        $this->ensureCanUpdate(
            excavatorLog: $excavatorLog,
            currentWorker: $currentWorker,
        );

        return $this->updateExcavatorLogAction->execute(
            excavatorLog: $excavatorLog,
            data: $data,
            currentWorker: $currentWorker,
            reason: $reason,
        );
    }

    public function delete(ExcavatorLog $excavatorLog, Worker $currentWorker, string $reason,): void
    {
        $this->ensureCompanyAccess(
            excavatorLog: $excavatorLog,
            currentWorker: $currentWorker,
        );

        $this->ensureCanUpdate(
            excavatorLog: $excavatorLog,
            currentWorker: $currentWorker,
        );

        $this->deleteExcavatorLogAction->execute(
            excavatorLog: $excavatorLog,
            currentWorker: $currentWorker,
            reason: $reason,
        );
    }

    private function ensureCompanyAccess(ExcavatorLog $excavatorLog, Worker $currentWorker,): void
    {
        if (
            $excavatorLog->machineAssignment->company_id
            !== $currentWorker->company_id
        ) {
            abort(404);
        }
    }

    private function ensureCanUpdate(ExcavatorLog $excavatorLog, Worker $currentWorker,): void
    {
        if ($currentWorker->isAdmin()) {
            return;
        }

        if ($currentWorker->isSiteManager() && $excavatorLog->machineAssignment->site_manager_id === $currentWorker->id)
        {
            return;
        }

        if ($currentWorker->isOperator() && $excavatorLog->worker_id === $currentWorker->id)
        {
            return;
        }

        abort(403);
    }
}
