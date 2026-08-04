<?php

namespace App\Models;

use App\Contracts\HasAttachments;
use App\Traits\InteractsWithAttachments;
use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Note extends Model implements HasAttachments
{
    use HasFactory;
    use SoftDeletes;
    use Loggable;
    use InteractsWithAttachments;

    protected $fillable = [
        'company_id',
        'daily_log_id',
        'construction_site_id',
        'site_manager_id',
        'note',
        'notify_admin',
        'date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'notify_admin' => 'boolean',
            'date' => 'date',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            Worker::class,
            'created_by'
        );
    }

    public function attachmentDailyLogId(): int
    {
        return $this->daily_log_id;
    }

    public function attachmentCompanyId(): int
    {
        return $this->company_id;
    }

    public function attachmentDate(): \Carbon\CarbonInterface
    {
        return $this->dailyLog->date;
    }
}
