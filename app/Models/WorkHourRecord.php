<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkHourRecord extends Model
{
    protected $fillable = [
        'attendance_import_id',
        'employee_code',
        'employee_name',
        'work_date',
        'work_minutes',
        'work_hours_text',
        'raw_data',
    ];

    protected $casts = [
        'work_date' => 'date',
        'raw_data' => 'array',
    ];

    public function import()
    {
        return $this->belongsTo(AttendanceImport::class, 'attendance_import_id');
    }
}