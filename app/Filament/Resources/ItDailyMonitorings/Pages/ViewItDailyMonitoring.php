<?php

namespace App\Filament\Resources\ItDailyMonitorings\Pages;

use App\Filament\Resources\ItDailyMonitorings\ItDailyMonitoringResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewItDailyMonitoring extends ViewRecord
{
    protected static string $resource = ItDailyMonitoringResource::class;

    protected static ?string $title = 'Rincian monitoring';

    public function getBreadcrumb(): string
    {
        return 'Rincian';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, 'format_version' => $this->getRecord()->formatVersion()];
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()->label('Ubah draft'), Action::make('repeat')->label('Periksa lagi')
            ->color('gray')->visible(fn (): bool => ItDailyMonitoringResource::canCreate())
            ->url(fn (): string => ItDailyMonitoringResource::getUrl('create', ['repeat' => $this->getRecord()->getKey()]))];
    }
}
