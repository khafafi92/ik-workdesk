<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkLocation extends Model
{
    protected $fillable = [
        'gps_name',
        'address',
        'latitude',
        'longitude',
        'radius_meters',
        'is_flexible',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_flexible' => 'boolean',
        'is_active' => 'boolean',
    ];
}
