<?php

namespace App\Actions\Worker;

use App\Models\Worker;
use App\Models\WorkerAttendance;

class ResetWorkerAvailabilityAction
{
    public function execute(): void
    {
        $workerIds = WorkerAttendance::query()
            ->whereDate('date', today()->subDay())
            ->distinct()
            ->pluck('worker_id');

        if ($workerIds->isEmpty())
        {
            return;
        }

        Worker::query()
            ->whereIn('id', $workerIds)
            ->update([
                'is_available' => true,
            ]);
    }
}
