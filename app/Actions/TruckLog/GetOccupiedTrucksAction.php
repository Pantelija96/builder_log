<?php

namespace App\Actions\TruckLog;

use App\Actions\BaseAction;
use App\Enums\MachineStatus;
use App\Enums\MachineType;
use App\Models\MachineAssignment;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class GetOccupiedTrucksAction extends BaseAction
{
    public function execute(
        Worker $currentWorker,
    ): Collection {
        $now = now();

        return MachineAssignment::query()
            ->where(
                'company_id',
                $currentWorker->company_id,
            )
            ->whereDate(
                'date',
                today(),
            )
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
                function (Builder $query) use ($now) {
                    $query
                        ->where(function (Builder $query) use ($now) {
                            $query
                                ->whereNull('site_manager_started_at')
                                ->orWhere(
                                    'site_manager_started_at',
                                    '<=',
                                    $now,
                                );
                        })
                        ->where(function (Builder $query) use ($now) {
                            $query
                                ->whereNull('site_manager_finished_at')
                                ->orWhere(
                                    'site_manager_finished_at',
                                    '>',
                                    $now,
                                );
                        });
                }
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
