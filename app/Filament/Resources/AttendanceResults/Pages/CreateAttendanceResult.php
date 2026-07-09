<?php

namespace App\Filament\Resources\AttendanceResults\Pages;

use App\Filament\Resources\AttendanceResults\AttendanceResultResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendanceResult extends CreateRecord
{
    protected static string $resource = AttendanceResultResource::class;
}
