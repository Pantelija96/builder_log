<?php

namespace App\QueryFilters;

use App\Models\Company;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendancePeriod
{
    public static function local(CarbonInterface $time): CarbonImmutable
    {
        return CarbonImmutable::instance($time)->setTimezone(config('app.timezone'))->startOfSecond();
    }

    public static function midnightAfter(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, config('app.timezone'))->startOfDay()->addDay();
    }

    public static function effectiveEnd(CarbonInterface $start, ?CarbonInterface $finish, string $date): CarbonImmutable
    {
        $end = $finish === null ? self::midnightAfter($date) : self::local($finish);

        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages(['finished_at' => 'The effective finish must be after the start.']);
        }

        return $end;
    }

    public static function overlaps(
        Builder $query, CarbonInterface $start, CarbonInterface $end,
        string $startColumn, string $endColumn, string $dateColumn,
    ): Builder {
        return self::endsAfter($query->where($startColumn, '<', $end), $start, $startColumn, $endColumn, $dateColumn);
    }

    public static function covers(
        Builder $query, CarbonInterface $at, string $startColumn, string $endColumn, string $dateColumn,
    ): Builder {
        return self::endsAfter($query->where($startColumn, '<=', $at), $at, $startColumn, $endColumn, $dateColumn);
    }

    private static function endsAfter(
        Builder $query, CarbonInterface $time, string $startColumn, string $endColumn, string $dateColumn,
    ): Builder {
        return $query->where(fn (Builder $period) => $period
            ->where(fn (Builder $closed) => $closed->where($endColumn, '>', $time)
                ->whereColumn($endColumn, '>', $startColumn))
            ->orWhere(fn (Builder $open) => $open->whereNull($endColumn)
                ->whereDate($dateColumn, '>=', $time->toDateString())));
    }

    public static function attendances(Builder $query, CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        return $end === null
            ? self::covers($query, $start, 'started_at', 'finished_at', 'worker_attendances.date')
            : self::overlaps($query, $start, $end, 'started_at', 'finished_at', 'worker_attendances.date');
    }

    public static function assignments(Builder $query, CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        $latestDate = $end?->avoidMutation()->subMicrosecond()->toDateString() ?? $start->toDateString();
        $startOperator = $end === null ? '<=' : '<';
        $latestStart = $end ?? $start;

        // Untimed worker bookings begin when made on their business date; earlier bookings reserve the date.
        return $query->withMachineLogMatching(fn (Builder $log) => $log->where(fn (Builder $period) => $period
            ->where(fn (Builder $pending) => $pending->whereNull('site_manager_started_at')
                ->whereDate('machine_assignments.date', '>=', $start->toDateString())
                ->whereDate('machine_assignments.date', '<=', $latestDate)
                ->where(fn (Builder $booking) => $booking->whereNull('machine_assignments.created_at')
                    ->orWhereDate('machine_assignments.created_at', '<>', DB::raw('date(machine_assignments.date)'))
                    ->orWhere('machine_assignments.created_at', $startOperator, $latestStart)))
            ->orWhere(fn (Builder $confirmed) => $end === null
                ? self::covers($confirmed, $start, 'site_manager_started_at', 'site_manager_finished_at', 'machine_assignments.date')
                : self::overlaps($confirmed, $start, $end, 'site_manager_started_at', 'site_manager_finished_at', 'machine_assignments.date'))));
    }

    public static function hasConflict(
        Worker $worker, CarbonInterface $start, ?CarbonInterface $end,
        ?int $exceptAttendance = null, ?int $exceptAssignment = null,
    ): bool {
        $attendances = $worker->attendances()->getQuery()->where('worker_attendances.company_id', $worker->company_id)
            ->when($exceptAttendance !== null, fn (Builder $query) => $query->whereKeyNot($exceptAttendance))
            ->when($exceptAssignment !== null && $exceptAttendance === null, fn (Builder $query) => $query
                ->where(fn (Builder $rows) => $rows->whereNull('machine_assignment_id')->orWhere('machine_assignment_id', '<>', $exceptAssignment)));
        $assignments = $worker->machineAssignments()->getQuery()->where('machine_assignments.company_id', $worker->company_id)
            ->when($exceptAssignment !== null, fn (Builder $query) => $query->whereKeyNot($exceptAssignment));

        return self::attendances($attendances, $start, $end)->exists()
            || self::assignments($assignments, $start, $end)->exists();
    }

    public static function lockWorker(int $companyId, int $workerId): Worker
    {
        // Machine writes already lock the company first. Share that order across write paths.
        Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();

        return Worker::withTrashed()->where('company_id', $companyId)->lockForUpdate()->findOrFail($workerId);
    }

    public static function syncAvailability(Worker $worker): void
    {
        if (! $worker->trashed()) {
            $now = self::local(now());
            $worker->update(['is_available' => ! self::hasConflict($worker, $now, null)]);
        }
    }
}
