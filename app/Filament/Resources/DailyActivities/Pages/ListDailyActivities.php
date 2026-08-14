<?php

namespace App\Filament\Resources\DailyActivities\Pages;

use App\Filament\Resources\DailyActivities\DailyActivityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDailyActivities extends ListRecords
{
    protected static string $resource = DailyActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Catat Pekerjaan'),
        ];
    }
}
