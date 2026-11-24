<?php

namespace App\Filament\Resources\ItDailyMonitorings\Pages;

use App\Filament\Resources\ItDailyMonitorings\ItDailyMonitoringResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewItDailyMonitoring extends ViewRecord
{
    protected static string $resource = ItDailyMonitoringResource::class;

    public function getTitle(): string
    {
        return __('Monitoring details');
    }

    public function getBreadcrumb(): string
    {
        return __('Details');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, 'format_version' => $this->getRecord()->formatVersion()];
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()->label(__('Change draft')), Action::make('repeat')->label(__('Check again'))
            ->color('gray')->visible(fn (): bool => ItDailyMonitoringResource::canCreate())
            ->url(fn (): string => ItDailyMonitoringResource::getUrl('create', ['repeat' => $this->getRecord()->getKey()]))];
    }
}
