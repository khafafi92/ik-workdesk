<?php

namespace App\Filament\Resources\VehicleBookings\Tables;

use App\Filament\Resources\VehicleBookings\VehicleBookingResource;
use App\Models\VehicleBooking;
use App\Services\VehicleBookingService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VehicleBookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('start_at')
                    ->label('Date & Time')
                    ->dateTime('d M Y H:i')
                    ->description(
                        fn (VehicleBooking $record): string => 'Until '.$record->end_at->format('H:i')
                    )
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Trip')
                    ->searchable()
                    ->description(
                        fn (VehicleBooking $record): string => $record->destination
                    )
                    ->wrap(),
                TextColumn::make('vehicle.name')
                    ->label('Vehicle')
                    ->searchable()
                    ->description(
                        fn (VehicleBooking $record): string => $record->vehicle?->plate_number ?? '-'
                    ),
                TextColumn::make('requester.name')
                    ->label('Requester')
                    ->searchable(),
                TextColumn::make('passengers_count')
                    ->label('Passengers')
                    ->badge(),
                TextColumn::make('display_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string => str($state)->title()->toString()
                    )
                    ->color(
                        fn (string $state): string => match ($state) {
                            'confirmed' => 'info',
                            'ongoing' => 'warning',
                            'completed' => 'success',
                            'cancelled' => 'gray',
                            default => 'gray',
                        }
                    ),
            ])
            ->filters([
                SelectFilter::make('vehicle_id')
                    ->label('Vehicle')
                    ->relationship('vehicle', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->options([
                        'confirmed' => 'Confirmed',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->defaultSort('start_at', 'desc')
            ->recordActions([
                EditAction::make()
                    ->visible(
                        fn (VehicleBooking $record): bool => VehicleBookingResource::canEdit($record)
                    ),
                Action::make('complete')
                    ->label('Set Done')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(
                        fn (VehicleBooking $record): bool => auth()->user()
                            ?->can('complete', $record) === true
                    )
                    ->requiresConfirmation()
                    ->modalDescription(
                        'Perjalanan akan ditandai selesai dan kendaraan langsung tersedia.'
                    )
                    ->action(
                        function (VehicleBooking $record): void {
                            app(VehicleBookingService::class)
                                ->complete($record, auth()->user());
                            Notification::make()
                                ->title('Trip marked as done')
                                ->success()
                                ->send();
                        }
                    ),
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(
                        fn (VehicleBooking $record): bool => auth()->user()
                            ?->can('cancel', $record) === true
                    )
                    ->form([
                        Textarea::make('reason')
                            ->label('Cancellation Reason')
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->action(
                        function (
                            VehicleBooking $record,
                            array $data
                        ): void {
                            app(VehicleBookingService::class)
                                ->cancel(
                                    $record,
                                    auth()->user(),
                                    $data['reason']
                                );
                            Notification::make()
                                ->title('Vehicle booking cancelled')
                                ->success()
                                ->send();
                        }
                    ),
                DeleteAction::make()
                    ->visible(
                        fn (VehicleBooking $record): bool => auth()->user()
                            ?->can('delete', $record) === true
                    ),
            ]);
    }
}
