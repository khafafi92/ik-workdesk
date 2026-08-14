<?php

namespace App\Filament\Resources\WorkProjects\Pages;

use App\Filament\Resources\WorkProjects\WorkProjectResource;
use Filament\Resources\Pages\EditRecord;

class EditWorkProject extends EditRecord
{
    protected static string $resource = WorkProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
