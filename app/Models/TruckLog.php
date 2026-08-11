<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TruckLog extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Loggable;

    protected $fillable = [
        'machine_id',
        'worker_id',
        'created_by',
        'date',
        'site_manager_started_at',
        'site_manager_finished_at',
        'operator_started_at',
        'operator_finished_at',
        'start_mileage',
        'end_mileage',
        'fuel_added',
        'fuel_remaining',
        'company_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'site_manager_started_at' => 'datetime',
            'site_manager_finished_at' => 'datetime',
            'operator_started_at' => 'datetime',
            'operator_finished_at' => 'datetime',
            'start_mileage' => 'decimal:2',
            'end_mileage' => 'decimal:2',
            'fuel_added' => 'decimal:2',
            'fuel_remaining' => 'decimal:2',
        ];
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(
            Worker::class,
            'worker_id',
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            Worker::class,
            'created_by',
        );
    }
}
