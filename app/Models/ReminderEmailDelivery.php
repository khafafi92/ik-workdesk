<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReminderEmailDelivery extends Model
{
    protected $fillable = [
        'reminder_id',
        'days_before',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'days_before' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function reminder()
    {
        return $this->belongsTo(Reminder::class);
    }
}
