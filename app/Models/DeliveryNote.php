<?php

namespace App\Models;

use App\Contracts\HasAttachments;
use App\Traits\InteractsWithAttachments;
use App\Traits\Loggable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryNote extends Model implements HasAttachments
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
        'supplier_id',
        'name',
        'description',
        'date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
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
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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

    public function attachmentDate(): CarbonInterface
    {
        return $this->dailyLog->date;
    }
}
