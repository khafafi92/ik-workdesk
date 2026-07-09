<?php

namespace App\Filament\Resources\WorkTasks\Pages;

use App\Filament\Resources\WorkTasks\WorkTaskResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkTasks extends ListRecords
{
    protected static string $resource = WorkTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
