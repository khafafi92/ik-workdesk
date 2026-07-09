<?php

namespace App\Filament\Resources\AttendanceResults\Pages;

use App\Filament\Resources\AttendanceResults\AttendanceResultResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceResults extends ListRecords
{
    protected static string $resource = AttendanceResultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
