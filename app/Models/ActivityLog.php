<?php

namespace App\Models;

use App\Enums\LogEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'daily_log_id',
        'construction_site_id',
        'actor_id',
        'event',
        'subject_type',
        'subject_id',
        'description',
        'date',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'event' => LogEvent::class,
            'date' => 'date',
            'created_at' => 'datetime',
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

    public function actor(): BelongsTo
    {
        return $this->belongsTo(
            Worker::class,
            'actor_id'
        );
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
