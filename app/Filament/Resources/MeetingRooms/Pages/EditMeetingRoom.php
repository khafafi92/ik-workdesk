<?php

namespace App\Filament\Resources\MeetingRooms\Pages;

use App\Filament\Resources\MeetingRooms\MeetingRoomResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMeetingRoom extends EditRecord
{
    protected static string $resource = MeetingRoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(
                    fn (): bool =>
                        ! $this->record->bookings()->exists()
                ),
        ];
    }
}
