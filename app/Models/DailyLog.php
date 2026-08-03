<?php

namespace App\Models;

use App\Traits\Loggable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Contracts\HasAttachments;
use App\Traits\InteractsWithAttachments;

class DailyLog extends Model implements HasAttachments
{
    use HasFactory;
    use SoftDeletes;
    use Loggable;
    use InteractsWithAttachments;

    protected $fillable = [
        'company_id',
        'construction_site_id',
        'site_manager_id',
        'date',
        'is_locked',
        'locked_at',
        'locked_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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

    public function isLocked(): bool
    {
        return $this->is_locked;
    }

    public function machineAssignments(): HasMany
    {
        return $this->hasMany(MachineAssignment::class);
    }

    public function workerAttendances(): HasMany
    {
        return $this->hasMany(WorkerAttendance::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function subcontractorLogs(): HasMany
    {
        return $this->hasMany(SubcontractorLog::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(
            Worker::class,
            'locked_by'
        );
    }

    public function isEditable(): bool
    {
        return ! $this->is_locked;
    }

    public function lock(?Worker $worker = null): void
    {
        $this->update([
            'is_locked' => true,
            'locked_at' => now(),
            'locked_by' => $worker?->id,
        ]);
    }

    public function unlock(): void
    {
        $this->update([
            'is_locked' => false,
            'locked_at' => null,
            'locked_by' => null,
        ]);
    }

    public function logContext(): array
    {
        return [
            'company_id' => $this->company_id,
            'daily_log_id' => $this->id,
            'construction_site_id' => $this->construction_site_id,
            'date' => $this->date,
        ];
    }

    public function attachmentDailyLogId(): int
    {
        return $this->id;
    }

    public function attachmentCompanyId(): int
    {
        return $this->company_id;
    }

    public function attachmentDate(): CarbonInterface
    {
        return $this->date;
    }
}
