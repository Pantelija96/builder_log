<?php

namespace App\Actions\MachineAssignment;

use App\Actions\BaseAction;
use App\DTO\MachineAssignment\CreateMachineAssignmentData;
use App\Exceptions\BusinessException;
use App\Models\DailyLog;
use App\Models\Machine;
use App\Models\MachineAssignment;
use App\Models\Worker;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class CreateMachineAssignmentAction extends BaseAction
{
    public function execute(
        DailyLog $dailyLog,
        CreateMachineAssignmentData $data,
        Worker $currentWorker,
    ): MachineAssignment {

        return $this->transaction(function () use (
            $dailyLog,
            $data,
            $currentWorker,
        ) {

            $machine = Machine::query()
                ->where('company_id', $currentWorker->company_id)
                ->whereKey($data->machineId)
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

            if ($this->hasOverlappingAssignment(
                machineId: $machine->id,
                startedAt: $data->startedAt,
                finishedAt: $data->finishedAt,
            )) {
                throw new BusinessException(
                    __('Machine is already assigned during this time period.')
                );
            }

            $assignment = MachineAssignment::create([
                'company_id' => $currentWorker->company_id,
                'daily_log_id' => $dailyLog->id,
                'construction_site_id' => $dailyLog->construction_site_id,
                'site_manager_id' => $dailyLog->site_manager_id,
                'machine_id' => $machine->id,
                'worker_id' => null,
                'started_at' => $data->startedAt,
                'finished_at' => $data->finishedAt,
                'date' => $dailyLog->date,
                'created_by' => $currentWorker->id,
            ]);

            return $assignment->fresh([
                'machine',
                'constructionSite',
                'siteManager',
                'worker',
                'creator',
            ]);
        });
    }

    private function hasOverlappingAssignment(
        int $machineId,
        CarbonInterface $startedAt,
        ?CarbonInterface $finishedAt,
    ): bool {

        $newEnd = $finishedAt
            ?? Carbon::create(
                9999,
                12,
                31,
                23,
                59,
                59,
            );

        return MachineAssignment::query()
            ->where('machine_id', $machineId)
            ->where('started_at', '<', $newEnd)
            ->where(function (Builder $query) use ($startedAt) {
                $query
                    ->whereNull('finished_at')
                    ->orWhere(
                        'finished_at',
                        '>',
                        $startedAt,
                    );
            })
            ->exists();
    }
}
