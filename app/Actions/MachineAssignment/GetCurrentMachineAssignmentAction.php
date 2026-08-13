<?php

namespace App\Actions\MachineAssignment;

use App\Actions\BaseAction;
use App\Models\MachineAssignment;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Builder;

class GetCurrentMachineAssignmentAction extends BaseAction
{
    public function execute(Worker $currentWorker,): ?MachineAssignment
    {
        $now = now();

        return MachineAssignment::query()
            ->where('company_id', $currentWorker->company_id,)
            ->where('worker_id', $currentWorker->id,)
            ->whereDate('date', today(),)
            ->whereHas(
                'excavatorLog',
                function (Builder $query) use ($now) {
                    $query
                        ->where(function (Builder $query) use ($now) {
                            $query
                                ->whereNull('site_manager_finished_at')
                                ->orWhere('site_manager_finished_at', '>', $now,);
                        })
                        ->where(function (Builder $query) use ($now) {
                            $query
                                ->whereNull('operator_finished_at')
                                ->orWhere('operator_finished_at', '>', $now,);
                        });
                }
            )
            ->with([
                'machine',
                'constructionSite',
                'siteManager',
                'worker',
                'creator',
                'excavatorLog',
            ])
            ->latest('id')
            ->first();
    }
}
