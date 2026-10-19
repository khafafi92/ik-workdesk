<?php

namespace App\Filament\Resources\ItDevices\Pages;

use App\Filament\Resources\ItDevices\ItDeviceResource;
use Filament\Resources\Pages\ViewRecord;

class ViewItDevice extends ViewRecord
{
    use InteractsWithItDeviceRecord;

    protected static string $resource = ItDeviceResource::class;

    protected string $view = 'filament.pages.it-device-record';
}
