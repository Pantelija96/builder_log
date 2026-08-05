<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Loggable;

    protected $fillable = [
        'company_id',
        'site_manager_id',
        'construction_site_id',
        'title',
        'description',
        'priority',
        'due_date',
        'read_at',
        'completed_at',
        'created_by',
        'completed_by'
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'read_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function siteManager(): BelongsTo
    {
        return $this->belongsTo(
            Worker::class,
            'site_manager_id'
        );
    }

    public function constructionSite(): BelongsTo
    {
        return $this->belongsTo(ConstructionSite::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            Worker::class,
            'created_by'
        );
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(
            Worker::class,
            'completed_by'
        );
    }

    public function markAsRead(): void
    {
        if ($this->isRead()) {
            return;
        }

        $this->update([
            'read_at' => now(),
        ]);
    }

    public function markAsCompleted(Worker $worker): void
    {
        if ($this->isCompleted()) {
            return;
        }

        $this->update([
            'completed_at' => now(),
            'completed_by' => $worker->id,
        ]);
    }

    public function reopen(): void
    {
        $this->update([
            'completed_at' => null,
            'completed_by' => null,
        ]);
    }
}
