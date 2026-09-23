<?php

namespace App\Filament\Resources\AttendanceImports\Pages;

use App\Filament\Pages\AttendanceReportCenter;
use App\Filament\Resources\AttendanceImports\AttendanceImportResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceImports extends ListRecords
{
    protected static string $resource = AttendanceImportResource::class;

    public function getTitle(): string
    {
        return 'Kelola upload attendance';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Upload data baru'),
            Action::make('reportCenter')->label('Kembali ke laporan')->color('gray')->url(AttendanceReportCenter::getUrl()),
        ];
    }
}
