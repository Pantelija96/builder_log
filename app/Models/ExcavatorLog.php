<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Loggable;

class ExcavatorLog extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Loggable;

    protected $fillable = [
        'machine_assignment_id',
        'worker_id',
        'created_by',

        'site_manager_started_at',
        'site_manager_finished_at',

        'operator_started_at',
        'operator_finished_at',

        'work_hours',
        'start_work_hours',
        'finish_work_hours',

        'fuel_added',
        'fuel_remaining',

        'note_site_manager',
        'note_operator',
    ];

    protected function casts(): array
    {
        return [
            'site_manager_started_at' => 'datetime',
            'site_manager_finished_at' => 'datetime',

            'operator_started_at' => 'datetime',
            'operator_finished_at' => 'datetime',

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
        return $this->belongsTo(Worker::class, 'created_by');
    }

    public function logContext(): array
    {
        $assignment = $this->machineAssignment;

        return [
            'company_id' => $assignment->company_id,
            'daily_log_id' => $assignment->daily_log_id,
            'construction_site_id' => $assignment->construction_site_id,
            'date' => $assignment->date,
        ];
    }
}
