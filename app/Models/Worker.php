<?php

namespace App\Models;

use App\Enums\WorkerRole;
use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Worker extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Loggable;
    use Notifiable;
    use SoftDeletes;

    protected $attributes = [
        'display_order' => 0,
    ];

    protected $fillable = [
        'display_order',
        'company_id',
        'first_name',
        'last_name',
        'phone',
        'role',
        'username',
        'password',
        'email',
        'is_active',
        'is_available',
        'hourly_rate',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'role' => WorkerRole::class,
            'password' => 'hashed',
            'hourly_rate' => 'decimal:2',
            'is_active' => 'boolean',
            'is_available' => 'boolean',
        ];
    }

    public function scopeInDisplayOrder(Builder $query): Builder
    {
        return $query->orderByRaw('CASE WHEN workers.display_order = 0 THEN 1 ELSE 0 END ASC')
            ->orderBy('workers.display_order')
            ->orderBy('workers.first_name')
            ->orderBy('workers.last_name')
            ->orderBy('workers.id');
    }

    public function scopeSiteManagers(Builder $query): Builder
    {
        return $query->where(
            'role',
            WorkerRole::SITE_MANAGER,
        );
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === WorkerRole::ADMIN;
    }

    public function isSiteManager(): bool
    {
        return $this->role === WorkerRole::SITE_MANAGER;
    }

    public function isOperator(): bool
    {
        return $this->role === WorkerRole::OPERATOR;
    }

    public function isDriver(): bool
    {
        return $this->role === WorkerRole::DRIVER;
    }

    public function isWorker(): bool
    {
        return $this->role === WorkerRole::WORKER;
    }

    public function participatedDailyLogs(): BelongsToMany
    {
        return $this->belongsToMany(DailyLog::class, 'daily_log_site_managers', 'site_manager_id', 'daily_log_id')
            ->withTimestamps();
    }

    public function machineAssignments(): HasMany
    {
        return $this->hasMany(
            MachineAssignment::class,
            'worker_id'
        );
    }

    public function createdMachineAssignments(): HasMany
    {
        return $this->hasMany(
            MachineAssignment::class,
            'created_by'
        );
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(
            WorkerAttendance::class,
            'worker_id'
        );
    }

    public function managedWorkerAttendances(): HasMany
    {
        return $this->hasMany(
            WorkerAttendance::class,
            'site_manager_id'
        );
    }

    public function createdWorkerAttendances(): HasMany
    {
        return $this->hasMany(
            WorkerAttendance::class,
            'created_by'
        );
    }

    public function uploadedDocuments(): HasMany
    {
        return $this->hasMany(
            Document::class,
            'uploaded_by'
        );
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(
            Task::class,
            'site_manager_id'
        );
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(
            Task::class,
            'created_by'
        );
    }

    public function cashAdvances(): HasMany
    {
        return $this->hasMany(
            CashAdvance::class,
            'site_manager_id'
        );
    }

    public function createdCashAdvances(): HasMany
    {
        return $this->hasMany(
            CashAdvance::class,
            'created_by'
        );
    }

    public function constructionSites(): BelongsToMany
    {
        return $this->belongsToMany(ConstructionSite::class)->withTimestamps();
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'site_manager_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(WorkerPayment::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(WorkerDevice::class);
    }

    public function activeDevices(): HasMany
    {
        return $this->hasMany(WorkerDevice::class)->where('is_active', true);
    }
}
