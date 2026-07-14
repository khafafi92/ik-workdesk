<?php

namespace App\Filament\Resources\WorkTasks\Pages;

use App\Filament\Resources\WorkTasks\WorkTaskResource;
use Filament\Resources\Pages\ListRecords;

class ListWorkTasks extends ListRecords
{
    protected static string $resource = WorkTaskResource::class;
}
