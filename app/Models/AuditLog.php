<?php

namespace App\Models;

use App\Enums\LogEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'actor_id',
        'event',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'event' => LogEvent::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(
            Worker::class,
            'actor_id'
        );
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
