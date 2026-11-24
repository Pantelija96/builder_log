<?php

namespace App\Actions\MachineAssignment;

use App\Actions\BaseAction;
use App\Models\MachineAssignment;
use App\Models\Worker;

class GetCurrentMachineAssignmentAction extends BaseAction
{
    public function execute(
        Worker $currentWorker,
        ?int $workerId = null,
    ): ?MachineAssignment {
        $now = now();

        $targetWorkerId =
            $workerId ?? $currentWorker->id;

        if ($workerId !== null) {
            abort_unless(Worker::query()->where('company_id', $currentWorker->company_id)->whereKey($workerId)->exists(), 404);
        }

        return MachineAssignment::query()
            ->visibleTo($currentWorker)
            ->where(
                'worker_id',
                $targetWorkerId,
            )
            ->occupiedAt($now)
            ->with([
                'machine',
                'machine.excavator',
                'machine.truck',
                'constructionSite',
                'siteManager',
                'worker',
                'creator',
                'excavatorLog',
                'truckLog',
            ])
            ->latest('id')
            ->first();
    }
}
