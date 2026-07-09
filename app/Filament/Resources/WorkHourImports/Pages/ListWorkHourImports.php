<?php

namespace App\Filament\Resources\WorkHourImports\Pages;

use App\Filament\Resources\WorkHourImports\WorkHourImportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkHourImports extends ListRecords
{
    protected static string $resource = WorkHourImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
