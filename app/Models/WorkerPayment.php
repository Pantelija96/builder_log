<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkerPayment extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Loggable;

    protected $fillable = [
        'company_id',
        'worker_id',
        'amount',
        'date',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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
