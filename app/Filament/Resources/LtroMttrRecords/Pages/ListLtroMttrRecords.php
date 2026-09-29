<?php

namespace App\Filament\Resources\LtroMttrRecords\Pages;

use App\Filament\Resources\LtroMttrRecords\LtroMttrRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLtroMttrRecords extends ListRecords
{
    protected static string $resource = LtroMttrRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
