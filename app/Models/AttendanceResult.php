<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceResult extends Model
{
    protected $fillable = [
        'attendance_import_id',

        'employee_code',
        'employee_name',
        'job_position',

        'attendance_date',
        'check_time',
        'check_type',

        'clock_in',
        'clock_out',

        'shift_name',
        'location_setting_name',
        'location_gps_name',
        'location_address',
        'location_coordinate',

        'description',
        'mobile_flag',
        'approval_status',

        'work_minutes',
        'duration_minutes',
        'duration_text',

        'distance_meters',
        'matched_location_name',

        'location_check',
        'checkout_check',

        'expected_checkout',
        'clock_in_status',
        'location_status',
        'checkout_status',
        'work_hour_status',
        'final_status',

        'notes',
    ];

    protected $casts = [
        'attendance_date' => 'date',
    ];

    public function import()
    {
        return $this->belongsTo(AttendanceImport::class, 'attendance_import_id');
    }
}