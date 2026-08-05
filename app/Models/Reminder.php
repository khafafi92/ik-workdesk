<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    protected $attributes = [
        'email_alarm_days' => '[3,1]',
    ];

    protected $fillable = [
        'employee_id',
        'department_id',
        'work_task_id',
        'reminder_type',
        'title',
        'description',
        'reminder_at',
        'status',
        'is_notified',
        'notified_at',
        'email_alarm_days',
    ];

    protected $casts = [
        'reminder_at' => 'datetime',
        'is_notified' => 'boolean',
        'notified_at' => 'datetime',
        'email_alarm_days' => 'array',
    ];

    public function setEmailAlarmDaysAttribute(?array $days): void
    {
        $this->attributes['email_alarm_days'] = $days === null
            ? null
            : json_encode(array_values(array_unique(array_map('intval', $days))));
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function workTask()
    {
        return $this->belongsTo(WorkTask::class);
    }

    public function emailDeliveries()
    {
        return $this->hasMany(ReminderEmailDelivery::class);
    }

    public function markAsDone(): void
    {
        if ($this->status === 'done') {
            return;
        }

        $this->update(['status' => 'done']);
    }
}
