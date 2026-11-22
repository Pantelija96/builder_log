<?php

namespace App\Actions\TruckLog;

use App\Enums\MachineStatus;
use App\Enums\MachineType;
use App\Models\Machine;
use App\Models\Worker;
use App\QueryFilters\MachinePeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class GetAvailableTrucksAction
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
                MachineType::TRUCK,
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
                        ->whereDate('date', $now->toDateString())
                        ->whereHas(
                            'truckLog',
                            fn (Builder $query) => MachinePeriod::occupiedAt($query, $now)
                        );
                }
            )
            ->with([
                'owner',
                'truck',
            ])
            ->get();
    }
}
