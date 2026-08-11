<?php

namespace App\Actions\Machine;

use App\Enums\MachineType;
use App\Models\Machine;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;

class GetAvailableMachinesAction
{
    public function execute(
        Worker $currentWorker,
        MachineType $type,
    ): Collection {

        $now = now();

        return Machine::query()
            ->where(
                'company_id',
                $currentWorker->company_id,
            )
            ->where(
                'type',
                $type,
            )
            ->where(
                'status',
                'active',
            )
            ->whereDoesntHave(
                'machineAssignments',
                function (Builder $query) use ($now) {

                    $query
                        ->where(function (Builder $query) use ($now) {
                            $query
                                ->whereNull(
                                    'site_manager_finished_at'
                                )
                                ->orWhere(
                                    'site_manager_finished_at',
                                    '>',
                                    $now,
                                );
                        })
                        ->where(function (Builder $query) use ($now) {
                            $query
                                ->whereNull(
                                    'operator_finished_at'
                                )
                                ->orWhere(
                                    'operator_finished_at',
                                    '>',
                                    $now,
                                );
                        });
                }
            )
            ->get();
    }
}
