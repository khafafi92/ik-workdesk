<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'attendance_import_id',
        'employee_name',
        'attendance_date',
        'clock_in',
        'clock_out',
        'location_gps_name',
        'location_address',
        'location_coordinate',
        'latitude',
        'longitude',
        'raw_data',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'raw_data' => 'array',
    ];

    public function import()
    {
        return $this->belongsTo(AttendanceImport::class, 'attendance_import_id');
    }
}