<?php

namespace App\Filament\Resources\WorkProjects\Pages;

use App\Filament\Resources\WorkProjects\WorkProjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkProjects extends ListRecords
{
    protected static string $resource = WorkProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
