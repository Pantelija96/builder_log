<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExcavatorLog extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'machine_assignment_id',
        'worker_id',
        'created_by',
        'started_at',
        'finished_at',
        'work_hours',
        'fuel_added',
        'fuel_remaining',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'work_hours' => 'decimal:2',
            'fuel_added' => 'decimal:2',
            'fuel_remaining' => 'decimal:2',
        ];
    }

    public function machineAssignment(): BelongsTo
    {
        return $this->belongsTo(MachineAssignment::class);
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
}
