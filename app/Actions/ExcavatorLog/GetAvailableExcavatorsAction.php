<?php

namespace App\Actions\ExcavatorLog;

use App\Enums\MachineStatus;
use App\Enums\MachineType;
use App\Models\Machine;
use App\Models\Worker;
use App\QueryFilters\MachinePeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class GetAvailableExcavatorsAction
{
    public function execute(
        Worker $currentWorker,
    ): Collection {
        $now = now();

        return Machine::query()
            ->where(
                'company_id',
                $currentWorker->company_id,
            )
            ->where(
                'type',
                MachineType::EXCAVATOR,
            )
            ->where(
                'status',
                MachineStatus::ACTIVE,
            )
            ->whereDoesntHave(
                'machineAssignments',
                function (Builder $assignmentQuery) use ($now, $currentWorker) {
                    $assignmentQuery
                        ->where('company_id', $currentWorker->company_id)
                        ->whereHas(
                            'excavatorLog',
                            fn (Builder $query) => MachinePeriod::occupiedAt($query, $now)
                        );
                }
            )
            ->with([
                'owner',
                'excavator',
            ])
            ->get();
    }
}
