<?php

namespace App\Actions\Worker;

use App\DTO\Worker\GetWorkerWorkHistoryData;
use App\DTO\Worker\WorkerWorkHistoryData;
use App\Enums\WorkerRole;
use App\Exceptions\BusinessException;
use App\Models\ExcavatorLog;
use App\Models\TruckLog;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use Illuminate\Database\Eloquent\Collection;

class GetWorkerWorkHistoryAction
{
    public function execute(Worker $worker, GetWorkerWorkHistoryData $data,): WorkerWorkHistoryData
    {
        [$type, $history] = match ($worker->role) {

            WorkerRole::WORKER => [
                'attendance',
                $this->getAttendance($worker, $data),
            ],

            WorkerRole::OPERATOR => [
                'excavator_log',
                $this->getExcavatorLogs($worker, $data),
            ],

            WorkerRole::DRIVER => [
                'truck_log',
                $this->getTruckLogs($worker, $data),
            ],

            default => throw new BusinessException(
                __('This worker role does not have work history.')
            ),
        };

        return new WorkerWorkHistoryData(
            worker: $worker,
            type: $type,
            history: $history,
        );
    }

    private function getAttendance(Worker $worker, GetWorkerWorkHistoryData $data,): Collection
    {
        return WorkerAttendance::query()
            ->where('worker_id', $worker->id)
            ->when(
                $data->dateFrom,
                fn ($query, $date) =>
                $query->whereDate('date', '>=', $date)
            )
            ->when(
                $data->dateTo,
                fn ($query, $date) =>
                $query->whereDate('date', '<=', $date)
            )
            ->with([
                'worker',
                'creator',
            ])
            ->orderBy('date')
            ->get();
    }

    private function getExcavatorLogs(Worker $worker, GetWorkerWorkHistoryData $data,): Collection
    {
        return ExcavatorLog::query()
            ->where('worker_id', $worker->id)
            ->whereHas(
                'machineAssignment',
                function ($query) use ($data) {
                    $query
                        ->when(
                            $data->dateFrom,
                            fn ($query, $date) =>
                            $query->whereDate('date', '>=', $date)
                        )
                        ->when(
                            $data->dateTo,
                            fn ($query, $date) =>
                            $query->whereDate('date', '<=', $date)
                        );
                }
            )
            ->with([
                'machineAssignment.machine',
                'machineAssignment.constructionSite',
                'machineAssignment.siteManager',
                'worker',
                'creator',
            ])
            ->orderByDesc('id')
            ->get();
    }

    private function getTruckLogs(Worker $worker, GetWorkerWorkHistoryData $data,): Collection
    {
        return TruckLog::query()
            ->where('worker_id', $worker->id)
            ->when(
                $data->dateFrom,
                fn ($query, $date) =>
                $query->whereDate('date', '>=', $date)
            )
            ->when(
                $data->dateTo,
                fn ($query, $date) =>
                $query->whereDate('date', '<=', $date)
            )
            ->with([
                'machine',
                'worker',
                'creator',
            ])
            ->orderBy('date')
            ->get();
    }
}
