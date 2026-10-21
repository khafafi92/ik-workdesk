<?php

namespace App\Filament\Resources\ItDailyMonitorings\Pages;

use App\Enums\ItMonitoringType;
use App\Filament\Resources\ItDailyMonitorings\ItDailyMonitoringResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItDailyMonitorings extends ListRecords
{
    protected static string $resource = ItDailyMonitoringResource::class;

    public function getBreadcrumb(): string
    {
        return 'Daftar';
    }

    protected function getHeaderActions(): array
    {
        return collect(ItMonitoringType::cases())->map(fn (ItMonitoringType $type): CreateAction => CreateAction::make('check_'.$type->value)
            ->label(match ($type) {
                ItMonitoringType::Internet => 'Cek internet',
                ItMonitoringType::Firewall => 'Cek firewall',
                ItMonitoringType::ServerRoom => 'Cek ruang server',
                ItMonitoringType::Synology => 'Cek backup',
                ItMonitoringType::Vsphere => 'Cek VM',
            })->color('gray')->url(ItDailyMonitoringResource::getUrl('create', ['type' => $type->value])))->all();
    }
}
