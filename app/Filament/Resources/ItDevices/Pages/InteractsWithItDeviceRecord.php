<?php

namespace App\Filament\Resources\ItDevices\Pages;

use App\Services\ItInfrastructure\ItInspectionService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Livewire\WithPagination;

trait InteractsWithItDeviceRecord
{
    use WithPagination;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, 'assignment_version' => $this->getRecord()->assignmentVersion(), ...Arr::only($this->getRecord()->computerDetail?->attributesToArray() ?? [], [
            'operating_system', 'windows_version', 'office_version', 'windows_license_status',
            'office_license_status', 'ram_gb', 'storage_devices',
        ])];
    }

    public function transferHistory()
    {
        Gate::authorize('view', $this->getRecord());

        return $this->getRecord()->transfers()->orderByDesc('transferred_on')->orderByDesc('id')->paginate(10, ['*'], 'transfersPage');
    }

    public function inspectionHistory()
    {
        return app(ItInspectionService::class)->queryFor(auth()->user())
            ->where('it_device_id', $this->getRecord()->id)->orderByDesc('checked_at')->orderByDesc('id')->limit(20)->get();
    }
}
