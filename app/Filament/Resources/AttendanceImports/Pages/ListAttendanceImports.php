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
        return __('Manage upload attendance');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('Upload new data')),
            Action::make('reportCenter')->label(__('Return to report'))->color('gray')->url(AttendanceReportCenter::getUrl()),
        ];
    }
}
