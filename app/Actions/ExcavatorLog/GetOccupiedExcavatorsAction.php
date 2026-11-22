<?php

namespace App\Actions\ExcavatorLog;

use App\Actions\BaseAction;
use App\Enums\MachineStatus;
use App\Enums\MachineType;
use App\Models\MachineAssignment;
use App\Models\Worker;
use App\QueryFilters\MachinePeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class GetOccupiedExcavatorsAction extends BaseAction
{
    public function execute(
        Worker $currentWorker,
    ): Collection {
        $now = now();

        return MachineAssignment::query()
            ->visibleTo($currentWorker)
            ->whereDate(
                'date',
                $now->toDateString(),
            )
            ->whereHas(
                'machine',
                function (Builder $query) {
                    $query
                        ->where(
                            'type',
                            MachineType::EXCAVATOR,
                        )
                        ->where(
                            'status',
                            MachineStatus::ACTIVE,
                        );
                }
            )
            ->whereHas(
                'excavatorLog',
                fn (Builder $query) => MachinePeriod::occupiedAt($query, $now)
            )
            ->with([
                'machine',
                'constructionSite',
                'worker',
                'siteManager',
                'excavatorLog',
                'machine.excavator',
            ])
            ->get();
    }
}
