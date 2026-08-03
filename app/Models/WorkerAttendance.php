<?php

namespace App\Models;

use App\Traits\Loggable;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkerAttendance extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Loggable;

    protected $fillable = [
        'company_id',
        'daily_log_id',
        'construction_site_id',
        'site_manager_id',
        'worker_id',
        'date',
        'started_at',
        'finished_at',
        'advance_payment',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'advance_payment' => 'decimal:2',
        ];
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

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            Worker::class,
            'created_by'
        );
    }

    public function getWorkedTimeAttribute(): ?string
    {
        if ($this->worked_minutes === null) {
            return null;
        }

        return CarbonInterval::minutes(
            $this->worked_minutes
        )->cascade()->forHumans([
            'short' => true,
        ]);
    }
}
