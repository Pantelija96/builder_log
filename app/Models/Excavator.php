<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Excavator extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'machine_id',
        'initial_work_hours',
    ];

    protected function casts(): array
    {
        return [
            'initial_work_hours' => 'decimal:2',
        ];
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
