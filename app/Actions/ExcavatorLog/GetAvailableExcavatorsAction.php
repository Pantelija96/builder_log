<?php

namespace App\Actions\ExcavatorLog;

use App\Enums\MachineStatus;
use App\Enums\MachineType;
use App\Models\Machine;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class GetAvailableExcavatorsAction
{
    public function execute(
        Worker $currentWorker,
    ): Collection {
        $now = now();

        return Machine::query()
            ->where('company_id',$currentWorker->company_id,)
            ->where('type', MachineType::EXCAVATOR,)
            ->where('status', MachineStatus::ACTIVE,)
            ->whereDoesntHave(
                'machineAssignments',
                function (Builder $assignmentQuery) use ($now) {
                    $assignmentQuery
                        ->whereDate('date', today())
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
