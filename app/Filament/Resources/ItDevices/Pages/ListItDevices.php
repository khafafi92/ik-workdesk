<?php

namespace App\Filament\Resources\ItDevices\Pages;

use App\Filament\Resources\ItDevices\ItDeviceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItDevices extends ListRecords
{
    protected static string $resource = ItDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Perangkat')];
    }
}
