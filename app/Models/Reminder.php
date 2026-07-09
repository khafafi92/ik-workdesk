<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    protected $fillable = [
        'employee_id',
        'department_id',
        'reminder_type',
        'title',
        'description',
        'reminder_at',
        'status',
        'is_notified',
        'notified_at',
    ];

    protected $casts = [
        'reminder_at' => 'datetime',
        'is_notified' => 'boolean',
        'notified_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}