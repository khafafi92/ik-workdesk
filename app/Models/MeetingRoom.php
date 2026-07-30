<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingRoom extends Model
{
    protected $fillable = [
        'name',
        'code',
        'location',
        'capacity',
        'description',
        'available_from',
        'available_until',
        'has_display',
        'has_projector',
        'has_video_conference',
        'has_whiteboard',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'has_display' => 'boolean',
            'has_projector' => 'boolean',
            'has_video_conference' => 'boolean',
            'has_whiteboard' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(MeetingBooking::class);
    }
}
