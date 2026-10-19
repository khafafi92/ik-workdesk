<?php

namespace App\Filament\Resources\ItDevices\Pages;

use App\Filament\Pages\ItInspectionPage;
use App\Filament\Resources\ItDevices\ItDeviceResource;
use App\Models\ItInspection;
use App\Services\ItInfrastructure\ItDeviceService;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class EditItDevice extends EditRecord
{
    use InteractsWithItDeviceRecord;

    protected static string $resource = ItDeviceResource::class;

    protected string $view = 'filament.pages.it-device-record';

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(ItDeviceService::class)->save(auth()->user(), $data, $record);
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('inspect')->label('Periksa perangkat ini')->url(fn () => ItInspectionPage::getUrl(['device' => $this->getRecord()->id]))->visible(fn () => Gate::allows('inspectDevice', [ItInspection::class, $this->getRecord()]))];
    }
}
