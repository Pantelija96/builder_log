<?php

namespace App\QueryFilters;

use App\Exceptions\BusinessException;
use App\Models\MachineAssignment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class MachinePeriod
{
    public static function occupiedAt(Builder $query, CarbonInterface $now): Builder
    {
        return self::whereReserved($query,
            fn (Builder $pending) => $pending->whereDate('machine_assignments.date', $now),
            fn (Builder $confirmed) => AttendancePeriod::covers($confirmed, $now,
                'site_manager_started_at', 'site_manager_finished_at', 'machine_assignments.date'),
        );
    }

    public static function validateManagerTimes(?CarbonInterface $start, ?CarbonInterface $finish): void
    {
        if ($finish !== null && ($start === null || $finish->lessThanOrEqualTo($start))) {
            throw ValidationException::withMessages([
                'site_manager_finished_at' => 'The manager finish requires a manager start and must be after it.',
            ]);
        }
    }

    public static function ensureAvailable(
        int $companyId,
        int $machineId,
        int $workerId,
        string $date,
        ?CarbonInterface $start,
        ?CarbonInterface $finish,
        string $machineMessage,
        string $workerMessage,
        ?int $exceptAssignmentId = null,
        bool $updating = false,
    ): void {
        $assignments = MachineAssignment::query()
            ->where('company_id', $companyId)
            ->when($exceptAssignmentId !== null, fn (Builder $query) => $query->whereKeyNot($exceptAssignmentId));

        $day = CarbonImmutable::parse($date, config('app.timezone'))->startOfDay();
        $machineStart = $start;

        if ($machineStart === null) {
            // New untimed bookings can claim the remainder of today after a finished shift.
            $machineStart = ! $updating && $day->isToday() ? AttendancePeriod::local(now()) : $day;
        }

        $machineEnd = AttendancePeriod::effectiveEnd($machineStart, $finish, $date);
        $machineConflict = fn (Builder $log) => self::whereReserved($log,
            fn (Builder $pending) => $pending->whereDate('machine_assignments.date', '>=', $machineStart)
                ->whereDate('machine_assignments.date', '<=', $machineEnd->subMicrosecond()),
            fn (Builder $confirmed) => AttendancePeriod::overlaps($confirmed, $machineStart, $machineEnd,
                'site_manager_started_at', 'site_manager_finished_at', 'machine_assignments.date'),
        );

        if ((clone $assignments)->where('machine_id', $machineId)->withMachineLogMatching($machineConflict)->exists()) {
            throw new BusinessException($machineMessage);
        }

        $worker = AttendancePeriod::lockWorker($companyId, $workerId);
        if (AttendancePeriod::hasConflict($worker, $machineStart, $machineEnd, exceptAssignment: $exceptAssignmentId)) {
            throw new BusinessException($workerMessage);
        }
    }

    private static function whereReserved(Builder $query, callable $pending, callable $confirmed): Builder
    {
        // Historical finish-only records have no authoritative interval and reserve their business date.
        return $query->where(fn (Builder $period) => $period
            ->where(fn (Builder $untimed) => $pending($untimed->whereNull('site_manager_started_at')))
            ->orWhere(fn (Builder $timed) => $confirmed($timed->whereNotNull('site_manager_started_at'))));
    }
}
