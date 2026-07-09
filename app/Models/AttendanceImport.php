<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceImport extends Model
{
    protected $fillable = [
        'uploaded_by_user_id',
        'period_name',
        'attendance_file_name',
        'attendance_file_path',
        'work_hour_file_name',
        'work_hour_file_path',
        'status',
        'notes',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function workHourRecords()
    {
        return $this->hasMany(WorkHourRecord::class);
    }

    public function results()
    {
        return $this->hasMany(AttendanceResult::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}