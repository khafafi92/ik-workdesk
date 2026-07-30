<?php

namespace App\Filament\Resources\VehicleBookings\Pages;

use App\Filament\Resources\VehicleBookings\VehicleBookingResource;
use App\Services\VehicleBookingService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateVehicleBooking extends CreateRecord
{
    protected static string $resource =
        VehicleBookingResource::class;

    protected function mutateFormDataBeforeCreate(
        array $data
    ): array {
        $user = auth()->user();
        $user?->loadMissing('employee');
        $data['requester_id'] = $user?->id;
        $data['department_id'] =
            $user?->employee?->department_id;

        return app(VehicleBookingService::class)
            ->normalizeScheduleData($data);
    }

    protected function handleRecordCreation(
        array $data
    ): Model {
        return app(VehicleBookingService::class)
            ->create($data);
    }

    protected function getRedirectUrl(): string
    {
        return VehicleBookingResource::getUrl('index');
    }
}
