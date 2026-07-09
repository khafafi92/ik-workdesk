<?php

namespace App\Filament\Resources\LocationReports\Pages;

use App\Filament\Resources\LocationReports\LocationReportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLocationReports extends ListRecords
{
    protected static string $resource = LocationReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
