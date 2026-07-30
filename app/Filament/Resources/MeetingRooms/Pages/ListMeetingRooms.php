<?php

namespace App\Filament\Resources\MeetingRooms\Pages;

use App\Filament\Resources\MeetingRooms\MeetingRoomResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMeetingRooms extends ListRecords
{
    protected static string $resource = MeetingRoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
