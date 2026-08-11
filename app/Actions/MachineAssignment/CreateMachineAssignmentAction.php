<?php

namespace App\Actions\MachineAssignment;

use App\Actions\BaseAction;
use App\DTO\MachineAssignment\CreateMachineAssignmentData;
use App\DTO\MachineAssignment\CreateMachineAssignmentForOperatorData;
use App\Exceptions\BusinessException;
use App\Models\ConstructionSite;
use App\Models\DailyLog;
use App\Models\Machine;
use App\Models\MachineAssignment;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Builder;

class CreateMachineAssignmentAction extends BaseAction
{
    /**
     * Create assignment from Site Manager / Daily Log context.
     */
    public function executeForDailyLog(
        DailyLog $dailyLog,
        CreateMachineAssignmentData $data,
        Worker $currentWorker,
    ): MachineAssignment {

        return $this->transaction(function () use (
            $dailyLog,
            $data,
            $currentWorker,
        ) {

            $machine = $this->findMachine(
                machineId: $data->machineId,
                companyId: $currentWorker->company_id,
            );

            $this->ensureMachineIsAvailable(
                machineId: $machine->id,
                startedAt: $data->siteManagerStartedAt,
            );

            $assignment = MachineAssignment::create([
                'company_id' => $currentWorker->company_id,

                'daily_log_id' => $dailyLog->id,

                'construction_site_id' =>
                    $dailyLog->construction_site_id,

                'site_manager_id' =>
                    $dailyLog->site_manager_id,

                'machine_id' =>
                    $machine->id,

                'worker_id' => null,

                'site_manager_started_at' =>
                    $data->siteManagerStartedAt,

                'site_manager_finished_at' =>
                    $data->siteManagerFinishedAt,

                'operator_started_at' => null,
                'operator_finished_at' => null,

                'date' => $dailyLog->date,

                'created_by' => $currentWorker->id,
            ]);

            return $this->freshAssignment($assignment);
        });
    }

    /**
     * Create assignment when Operator selects a machine himself.
     */
    public function executeForOperator(
        CreateMachineAssignmentForOperatorData $data,
        Worker $currentWorker,
    ): MachineAssignment {

        return $this->transaction(function () use (
            $data,
            $currentWorker,
        ) {

            if (! $currentWorker->isOperator()) {
                throw new BusinessException(
                    __('Only operators can create a machine assignment this way.')
                );
            }

            $constructionSite = ConstructionSite::query()
                ->where(
                    'company_id',
                    $currentWorker->company_id,
                )
                ->whereKey($data->constructionSiteId)
                ->first();

            if (! $constructionSite) {
                throw new BusinessException(
                    __('Construction site not found.')
                );
            }

            $machine = $this->findMachine(
                machineId: $data->machineId,
                companyId: $currentWorker->company_id,
            );

            /*
             * The operator is starting a new machine session now.
             *
             * We therefore check whether the machine is still
             * occupied by an existing assignment.
             */
            $this->ensureMachineIsAvailable(
                machineId: $machine->id,
                startedAt: now(),
            );

            /*
             * A DailyLog may already exist for this construction site
             * today. If it does, connect the assignment to it.
             *
             * If it does not exist yet, daily_log_id remains NULL.
             */
            $dailyLog = DailyLog::query()
                ->where(
                    'company_id',
                    $currentWorker->company_id,
                )
                ->where(
                    'construction_site_id',
                    $constructionSite->id,
                )
                ->whereDate(
                    'date',
                    today(),
                )
                ->first();

            $assignment = MachineAssignment::create([
                'company_id' => $currentWorker->company_id,

                'daily_log_id' => $dailyLog?->id,

                'construction_site_id' =>
                    $constructionSite->id,

                'site_manager_id' =>
                    $dailyLog?->site_manager_id,

                'machine_id' =>
                    $machine->id,

                'worker_id' =>
                    $currentWorker->id,

                'site_manager_started_at' => null,
                'site_manager_finished_at' => null,

                'operator_started_at' => null,
                'operator_finished_at' => null,

                'date' => today(),

                'created_by' => $currentWorker->id,
            ]);

            return $this->freshAssignment($assignment);
        });
    }

    private function findMachine(
        int $machineId,
        int $companyId,
    ): Machine {

        $machine = Machine::query()
            ->where(
                'company_id',
                $companyId,
            )
            ->whereKey($machineId)
            ->first();

        if (! $machine) {
            throw new BusinessException(
                __('Machine not found.')
            );
        }

        if (! $machine->isActive()) {
            throw new BusinessException(
                __('Machine is not active.')
            );
        }

        return $machine;
    }

    private function ensureMachineIsAvailable(
        int $machineId,
            $startedAt,
    ): void {

        $hasActiveAssignment = MachineAssignment::query()
            ->where('machine_id', $machineId)
            ->where(function (Builder $query) use ($startedAt) {
                $query
                    ->whereNull('site_manager_finished_at')
                    ->orWhere(
                        'site_manager_finished_at',
                        '>',
                        $startedAt,
                    );
            })
            ->where(function (Builder $query) use ($startedAt) {
                $query
                    ->whereNull('operator_finished_at')
                    ->orWhere(
                        'operator_finished_at',
                        '>',
                        $startedAt,
                    );
            })
            ->exists();

        if ($hasActiveAssignment) {
            throw new BusinessException(
                __('Machine is currently in use.')
            );
        }
    }

    private function freshAssignment(
        MachineAssignment $assignment,
    ): MachineAssignment {

        return $assignment->fresh([
            'machine',
            'constructionSite',
            'dailyLog',
            'siteManager',
            'worker',
            'creator',
        ]);
    }
}
