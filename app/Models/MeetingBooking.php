<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MeetingBooking extends Model
{
    protected $fillable = [
        'meeting_room_id',
        'organizer_id',
        'department_id',
        'title',
        'agenda',
        'start_at',
        'end_at',
        'meeting_type',
        'meeting_link',
        'external_guests',
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
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(
            MeetingRoom::class,
            'meeting_room_id'
        );
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'organizer_id'
        );
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cancelled_by'
        );
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'completed_by'
        );
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'meeting_booking_participants'
        )
            ->withPivot('attendance_status')
            ->withTimestamps();
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
