<?php

namespace App\Actions\TruckLog;

use App\Actions\BaseAction;
use App\Enums\MachineStatus;
use App\Enums\MachineType;
use App\Models\MachineAssignment;
use App\Models\Worker;
use App\QueryFilters\MachinePeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class GetOccupiedTrucksAction extends BaseAction
{
    public function execute(
        Worker $currentWorker,
    ): Collection {
        $now = now();

        return MachineAssignment::query()
            ->visibleTo($currentWorker)
            ->whereHas(
                'machine',
                function (Builder $query) {
                    $query
                        ->where(
                            'type',
                            MachineType::TRUCK,
                        )
                        ->where(
                            'status',
                            MachineStatus::ACTIVE,
                        );
                }
            )
            ->whereHas(
                'truckLog',
                fn (Builder $query) => MachinePeriod::occupiedAt($query, $now)
            )
            ->with([
                'machine',
                'constructionSite',
                'worker',
                'siteManager',
                'truckLog',
                'machine.truck',
            ])
            ->get();
    }
}
