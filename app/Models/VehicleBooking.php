<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleBooking extends Model
{
    protected $fillable = [
        'vehicle_id',
        'requester_id',
        'department_id',
        'title',
        'destination',
        'purpose',
        'passengers_count',
        'driver_name',
        'start_at',
        'end_at',
        'status',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'completed_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
            'passengers_count' => 'integer',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn(
            'status',
            ['cancelled', 'rejected']
        );
    }

    public function getDisplayStatusAttribute(): string
    {
        if (in_array(
            $this->status,
            ['cancelled', 'rejected', 'completed'],
            true
        )) {
            return $this->status;
        }

        if ($this->start_at?->isFuture()) {
            return 'confirmed';
        }

        if ($this->end_at?->isFuture()) {
            return 'ongoing';
        }

        return 'completed';
    }

    public function canBeCancelled(): bool
    {
        return $this->status === 'confirmed'
            && $this->start_at?->isFuture() === true;
    }

    public function canBeCompleted(): bool
    {
        return $this->status === 'confirmed'
            && $this->start_at?->lte(now()) === true
            && $this->end_at?->gt(now()) === true;
    }
}
