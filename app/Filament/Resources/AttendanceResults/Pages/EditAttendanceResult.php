<?php

namespace App\Filament\Resources\AttendanceResults\Pages;

use App\Filament\Resources\AttendanceResults\AttendanceResultResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAttendanceResult extends EditRecord
{
    protected static string $resource = AttendanceResultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
