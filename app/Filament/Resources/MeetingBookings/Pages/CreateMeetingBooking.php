<?php

namespace App\Filament\Resources\MeetingBookings\Pages;

use App\Filament\Resources\MeetingBookings\MeetingBookingResource;
use App\Models\MeetingBooking;
use App\Services\MeetingBookingService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMeetingBooking extends CreateRecord
{
    protected static string $resource = MeetingBookingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $user?->loadMissing('employee');

        $data['organizer_id'] = $user?->id;
        $data['department_id'] = $user?->employee?->department_id;

        return app(MeetingBookingService::class)
            ->normalizeScheduleData($data);
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(MeetingBookingService::class)->create($data);
    }

    protected function afterCreate(): void
    {
        app(MeetingBookingService::class)
            ->notifyParticipants(
                $this->record->fresh([
                    'room',
                    'organizer',
                    'participants',
                ])
            );
    }

    protected function getRedirectUrl(): string
    {
        return MeetingBookingResource::getUrl('index');
    }
}
