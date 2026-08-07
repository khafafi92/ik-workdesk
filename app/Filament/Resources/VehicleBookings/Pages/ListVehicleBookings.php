<?php

namespace App\Filament\Resources\VehicleBookings\Pages;

use App\Filament\Pages\VehicleBookingCalendar;
use App\Filament\Resources\VehicleBookings\VehicleBookingResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListVehicleBookings extends ListRecords
{
    protected static string $resource =
        VehicleBookingResource::class;

    public function getTabs(): array
    {
        $tabs = [
            'my-bookings' => Tab::make('My Bookings')
                ->modifyQueryUsing(
                    fn (Builder $query): Builder => $query->where(
                        'requester_id',
                        auth()->id()
                    )
                ),
        ];

        if (VehicleBookingResource::canManageAll()) {
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
                ->url(VehicleBookingCalendar::getUrl()),
            CreateAction::make()
                ->label('Book Vehicle'),
        ];
    }
}
