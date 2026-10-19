<?php

namespace App\Filament\Resources\ItDevices\Pages;

use App\Services\ItInfrastructure\ItInspectionService;
use Illuminate\Support\Arr;

trait InteractsWithItDeviceRecord
{
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, ...Arr::only($this->getRecord()->computerDetail?->attributesToArray() ?? [], [
            'operating_system', 'windows_version', 'office_version', 'windows_license_status', 'windows_verification_notes',
            'office_license_status', 'office_verification_notes', 'ram_gb', 'storage_devices',
        ])];
    }

    public function inspectionHistory()
    {
        return app(ItInspectionService::class)->queryFor(auth()->user())
            ->where('it_device_id', $this->getRecord()->id)->orderByDesc('checked_at')->orderByDesc('id')->limit(20)->get();
    }
}
