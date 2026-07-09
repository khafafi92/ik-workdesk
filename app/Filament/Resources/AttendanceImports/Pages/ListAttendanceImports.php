<?php

namespace App\Filament\Resources\AttendanceImports\Pages;

use App\Filament\Resources\AttendanceImports\AttendanceImportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceImports extends ListRecords
{
    protected static string $resource = AttendanceImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
