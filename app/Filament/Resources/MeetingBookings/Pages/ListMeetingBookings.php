<?php

namespace App\Filament\Resources\MeetingBookings\Pages;

use App\Filament\Pages\MeetingRoomCalendar;
use App\Filament\Resources\MeetingBookings\MeetingBookingResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMeetingBookings extends ListRecords
{
    protected static string $resource = MeetingBookingResource::class;

    public function getTabs(): array
    {
        $tabs = [
            'my-bookings' => Tab::make('My Bookings')
                ->modifyQueryUsing(
                    fn (Builder $query): Builder => $query->where(
                        fn (Builder $bookingQuery) => $bookingQuery
                            ->where(
                                'organizer_id',
                                auth()->id()
                            )
                            ->orWhereHas(
                                'participants',
                                fn (Builder $participantQuery) => $participantQuery->whereKey(
                                    auth()->id()
                                )
                            )
                    )
                ),
        ];

        if (MeetingBookingResource::canManageAll()) {
            $tabs['all-bookings'] = Tab::make('All Bookings');
        }

        return $tabs;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calendar')
                ->label('Open Calendar')
                ->icon('heroicon-o-calendar-days')
                ->url(MeetingRoomCalendar::getUrl()),
            CreateAction::make()
                ->label('Book Meeting Room'),
        ];
    }
}
