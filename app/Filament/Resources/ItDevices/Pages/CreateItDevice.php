<?php

namespace App\Filament\Resources\ItDevices\Pages;

use App\Filament\Resources\ItDevices\ItDeviceResource;
use App\Services\ItInfrastructure\ItDeviceService;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateItDevice extends CreateRecord
{
    protected static string $resource = ItDeviceResource::class;

    protected static ?string $title = 'Tambah Perangkat';

    protected string $view = 'filament.pages.it-device-record';

    protected function handleRecordCreation(array $data): Model
    {
        return app(ItDeviceService::class)->save(auth()->user(), $data);
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Simpan Perangkat');
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()->label('Simpan & tambah lagi');
    }

    protected function getRedirectUrl(): string
    {
        return ItDeviceResource::getUrl('edit', ['record' => $this->record]);
    }
}
