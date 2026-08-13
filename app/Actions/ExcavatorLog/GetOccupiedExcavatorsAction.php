<?php

namespace App\Actions\ExcavatorLog;

use App\Actions\BaseAction;
use App\Enums\MachineStatus;
use App\Enums\MachineType;
use App\Models\MachineAssignment;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class GetOccupiedExcavatorsAction extends BaseAction
{
    public function execute(Worker $currentWorker,): Collection
    {
        $now = now();

        return MachineAssignment::query()
            ->where('company_id', $currentWorker->company_id,
            )
            ->whereDate('date', today(),)
            ->whereHas(
                'machine',
                function (Builder $query) {
                    $query
                        ->where('type', MachineType::EXCAVATOR,)
                        ->where('status', MachineStatus::ACTIVE,);
                }
            )
            ->whereHas(
                'excavatorLog',
                function (Builder $query) use ($now) {
                    $query
                        ->where(function (Builder $query) use ($now) {
                            $query
                                ->whereNull('site_manager_finished_at')
                                ->orWhere('site_manager_finished_at', '>', $now,);
                        })
                        ->orWhere(function (Builder $query) use ($now) {
                            $query
                                ->whereNull('operator_finished_at')
                                ->orWhere('operator_finished_at', '>', $now,);
                        });
                }
            )
            ->with([
                'machine',
                'constructionSite',
                'worker',
                'siteManager',
                'excavatorLog',
            ])
            ->get();
    }
}
