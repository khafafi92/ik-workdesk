<?php

namespace App\Filament\Resources\ItDailyMonitorings\Pages;

use App\Filament\Resources\ItDailyMonitorings\ItDailyMonitoringResource;
use App\Services\ItInfrastructure\ItDailyMonitoringService;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditItDailyMonitoring extends EditRecord
{
    protected static string $resource = ItDailyMonitoringResource::class;

    protected static ?string $title = 'Ubah draft monitoring';

    public function getBreadcrumb(): string
    {
        return 'Ubah draft';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, 'format_version' => $this->getRecord()->formatVersion()];
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Simpan Draft')->color('gray');
    }

    protected function getFormActions(): array
    {
        return [$this->getSaveFormAction()->submit(null)->action(function (): void {
            $this->data['finalize'] = false;
            $this->save();
        }), Action::make('finalize')->label('Finalisasi')->action(function (): void {
            $this->data['finalize'] = true;
            $this->save();
        }), $this->getCancelFormAction()];
    }

    protected function getCancelFormAction(): Action
    {
        return Action::make('cancel')->label('Batal')->color('gray')->url(ItDailyMonitoringResource::getUrl('index'));
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(ItDailyMonitoringService::class)->save(auth()->user(), $data, $record->id);
    }

    protected function messages(): array
    {
        return ItDailyMonitoringService::validationMessages('data.');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Catatan monitoring tersimpan';
    }

    protected function getRedirectUrl(): string
    {
        return ItDailyMonitoringResource::getUrl('view', ['record' => $this->record]);
    }
}
