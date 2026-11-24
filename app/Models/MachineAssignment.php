<?php

namespace App\Models;

use App\Enums\LogEvent;
use App\QueryFilters\MachinePeriod;
use App\Traits\Loggable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class MachineAssignment extends Model
{
    use HasFactory;
    use Loggable;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'daily_log_id',
        'construction_site_id',
        'site_manager_id',
        'machine_id',
        'worker_id',
        'date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function scopeWithinCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('machine_assignments.company_id', $companyId)
            ->whereHas('machine', fn (Builder $machine) => $machine->where('company_id', $companyId))
            ->whereHas('worker', fn (Builder $worker) => $worker->withTrashed()->where('company_id', $companyId))
            ->whereHas('constructionSite', fn (Builder $site) => $site->withTrashed()->where('company_id', $companyId));
    }

    public function scopeVisibleTo(Builder $query, Worker $actor): Builder
    {
        $query->withinCompany($actor->company_id);
        $dailyLogs = DailyLog::query()->visibleTo($actor)
            ->whereColumn('daily_logs.construction_site_id', 'machine_assignments.construction_site_id')
            ->whereDate('daily_logs.date', DB::raw('date(machine_assignments.date)'));

        if ($actor->isSiteManager()) {
            return $query->whereExists($dailyLogs
                ->where(fn (Builder $logs) => $logs
                    ->whereColumn('daily_logs.id', 'machine_assignments.daily_log_id')
                    ->orWhereNull('machine_assignments.daily_log_id'))
                ->selectRaw('1')
                ->toBase());
        }

        $query->where(fn (Builder $assignments) => $assignments
            ->whereNull('machine_assignments.daily_log_id')
            ->orWhereExists($dailyLogs->whereColumn('daily_logs.id', 'machine_assignments.daily_log_id')->selectRaw('1')->toBase()));

        if (! $actor->isAdmin()) {
            $query->where('machine_assignments.worker_id', $actor->id);
        }

        return $query;
    }

    public function scopeOccupiedAt(Builder $query, CarbonInterface $now): Builder
    {
        return $query->withMachineLogMatching(fn (Builder $log) => MachinePeriod::occupiedAt($log, $now));
    }

    public function scopeWithMachineLogMatching(Builder $query, callable $condition): Builder
    {
        return $query->where(fn (Builder $assignments) => $assignments
            ->whereHas('excavatorLog', $condition)
            ->orWhereHas('truckLog', $condition));
    }

    public function isPendingSelfAssignment(): bool
    {
        return $this->created_by === $this->worker_id
            && ! $this->hasManagerTakeover()
            && ! $this->workerAttendance()->withTrashed()->exists();
    }

    private function hasManagerTakeover(): bool
    {
        // Older pending self-assignments may already have a legacy manager ID.
        return AuditLog::query()
            ->where('company_id', $this->company_id)
            ->where('auditable_type', $this->getMorphClass())
            ->where('auditable_id', $this->id)
            ->where('event', LogEvent::MACHINE_ASSIGNMENT_TAKEN_OVER)
            ->exists();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function dailyLog(): BelongsTo
    {
        return $this->belongsTo(DailyLog::class);
    }

    public function constructionSite(): BelongsTo
    {
        return $this->belongsTo(ConstructionSite::class);
    }

    public function siteManager(): BelongsTo
    {
        return $this->belongsTo(
            Worker::class,
            'site_manager_id'
        );
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class)->withTrashed();
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(
            Worker::class,
            'worker_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            Worker::class,
            'created_by'
        );
    }

    public function excavatorLog(): HasOne
    {
        return $this->hasOne(ExcavatorLog::class);
    }

    public function truckLog(): HasOne
    {
        return $this->hasOne(TruckLog::class);
    }

    public function excavatorLogWithTrashed(): HasOne
    {
        return $this->hasOne(ExcavatorLog::class)->withTrashed();
    }

    public function truckLogWithTrashed(): HasOne
    {
        return $this->hasOne(TruckLog::class)->withTrashed();
    }

    public function workerAttendance(): HasOne
    {
        return $this->hasOne(WorkerAttendance::class);
    }
}
