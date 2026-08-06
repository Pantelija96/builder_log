<?php

namespace App\Models;

use App\Enums\MachineStatus;
use App\Enums\MachineType;
use App\Enums\OwnerType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Machine extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'owner_type',
        'owner_id',
        'status',
        'image_path',
    ];

    protected function casts(): array
    {
        return [
            'type' => MachineType::class,
            'status' => MachineStatus::class,
            'owner_type' => OwnerType::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function excavator(): HasOne
    {
        return $this->hasOne(Excavator::class);
    }

    public function truck(): HasOne
    {
        return $this->hasOne(Truck::class);
    }

    public function isExcavator(): bool
    {
        return $this->type === MachineType::EXCAVATOR;
    }

    public function isTruck(): bool
    {
        return $this->type === MachineType::TRUCK;
    }

    public function isActive(): bool
    {
        return $this->status === MachineStatus::ACTIVE;
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(MachineAssignment::class);
    }

    public function isOwnedByCompany(): bool
    {
        return $this->owner_type === OwnerType::COMPANY;
    }

    public function isOwnedByWorker(): bool
    {
        return $this->owner_type === OwnerType::COMPANY;
    }
}
