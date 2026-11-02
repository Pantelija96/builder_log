<?php

namespace App\Actions\MachineAssignment;

use App\Actions\BaseAction;
use App\Models\MachineAssignment;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Builder;

class GetCurrentMachineAssignmentAction extends BaseAction
{
    public function execute(
        Worker $currentWorker,
        ?int $workerId = null,
    ): ?MachineAssignment {
        $now = now();

        $targetWorkerId =
            $workerId ?? $currentWorker->id;

        return MachineAssignment::query()
            ->where(
                'company_id',
                $currentWorker->company_id,
            )
            ->where(
                'worker_id',
                $targetWorkerId,
            )
            ->whereDate(
                'date',
                today(),
            )
            ->where(
                function (Builder $query) use ($now) {
                    $query
                        ->whereHas(
                            'excavatorLog',
                            fn (Builder $query) =>
                            $this->activeLog(
                                $query,
                                $now,
                            )
                        )
                        ->orWhereHas(
                            'truckLog',
                            fn (Builder $query) =>
                            $this->activeLog(
                                $query,
                                $now,
                            )
                        );
                }
            )
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

    private function activeLog(
        Builder $query,
        mixed $now,
    ): void {
        $query->where(
            function (Builder $query) use ($now) {
                /*
                 * Ako Site Manager nije uneo kompletan period,
                 * assignment ostaje current/occupied.
                 *
                 * Tek kada postoje oba manager vremena gledamo
                 * da li je "now" unutar potvrđenog perioda.
                 */
                $query
                    ->whereNull('site_manager_started_at')
                    ->orWhereNull('site_manager_finished_at')
                    ->orWhere(
                        function (Builder $query) use ($now) {
                            $query
                                ->where(
                                    'site_manager_started_at',
                                    '<=',
                                    $now,
                                )
                                ->where(
                                    'site_manager_finished_at',
                                    '>',
                                    $now,
                                );
                        }
                    );
            }
        );
    }
}
