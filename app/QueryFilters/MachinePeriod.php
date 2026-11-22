<?php

namespace App\QueryFilters;

use App\Exceptions\BusinessException;
use App\Models\MachineAssignment;
use Illuminate\Database\Eloquent\Builder;

class MachinePeriod
{
    public static function occupiedAt(Builder $query, mixed $now): Builder
    {
        return $query->where(fn (Builder $period) => $period
            ->whereNull('site_manager_started_at')
            ->orWhereNull('site_manager_finished_at')
            ->orWhere(fn (Builder $confirmed) => $confirmed
                ->where('site_manager_started_at', '<=', $now)
                ->where('site_manager_finished_at', '>', $now)));
    }

    public static function ensureAvailable(
        int $companyId,
        int $machineId,
        int $workerId,
        string $date,
        mixed $start,
        mixed $finish,
        string $machineMessage,
        string $workerMessage,
        ?int $exceptAssignmentId = null,
    ): void {
        $conflicts = fn (Builder $log) => self::conflictsWith($log, $start, $finish);
        $assignments = MachineAssignment::query()
            ->where('company_id', $companyId)
            ->whereDate('date', $date)
            ->when($exceptAssignmentId !== null, fn (Builder $query) => $query->whereKeyNot($exceptAssignmentId))
            ->where(fn (Builder $query) => $query
                ->whereHas('excavatorLog', $conflicts)
                ->orWhereHas('truckLog', $conflicts));

        if ((clone $assignments)->where('machine_id', $machineId)->exists()) {
            throw new BusinessException($machineMessage);
        }

        if ($assignments->where('worker_id', $workerId)->exists()) {
            throw new BusinessException($workerMessage);
        }
    }

    private static function conflictsWith(Builder $query, mixed $start, mixed $finish): void
    {
        if ($start === null) {
            return;
        }

        // An unconfirmed or unfinished reservation blocks another assignment on that day.
        $query->where(fn (Builder $period) => $period
            ->whereNull('site_manager_started_at')
            ->orWhereNull('site_manager_finished_at')
            ->orWhere(fn (Builder $confirmed) => $confirmed
                ->when($finish !== null, fn (Builder $bounded) => $bounded->where('site_manager_started_at', '<', $finish))
                ->where('site_manager_finished_at', '>', $start)));
    }
}
