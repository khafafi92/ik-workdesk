<?php

namespace App\Filament\Resources\MeetingBookings\Tables;

use App\Filament\Resources\MeetingBookings\MeetingBookingResource;
use App\Models\MeetingBooking;
use App\Services\MeetingBookingService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MeetingBookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('start_at')
                    ->label('Date & Time')
                    ->dateTime('d M Y H:i')
                    ->description(
                        fn (MeetingBooking $record): string => 'Until '.$record->end_at->format('H:i')
                    )
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Meeting')
                    ->searchable()
                    ->description(
                        fn (MeetingBooking $record): string => $record->organizer?->name ?? '-'
                    )
                    ->wrap(),
                TextColumn::make('room.name')
                    ->label('Room')
                    ->searchable()
                    ->description(
                        fn (MeetingBooking $record): string => $record->room?->location ?? '-'
                    ),
                TextColumn::make('participants_count')
                    ->label('Participants')
                    ->counts('participants')
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
                            'cancelled', 'rejected' => 'gray',
                            default => 'gray',
                        }
                    ),
            ])
            ->filters([
                SelectFilter::make('meeting_room_id')
                    ->label('Room')
                    ->relationship('room', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->options([
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->defaultSort('start_at', 'desc')
            ->recordActions([
                EditAction::make()
                    ->visible(
                        fn (MeetingBooking $record): bool => MeetingBookingResource::canEdit($record)
                    ),
                Action::make('complete')
                    ->label('Set Done')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(
                        function (MeetingBooking $record): bool {
                            $user = auth()->user();

                            return $user?->can(
                                'complete',
                                $record
                            ) === true;
                        }
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Finish this meeting?')
                    ->modalDescription(
                        'Meeting akan ditandai selesai dan sisa jadwal ruangan langsung tersedia untuk booking lain.'
                    )
                    ->action(
                        function (MeetingBooking $record): void {
                            app(MeetingBookingService::class)
                                ->complete(
                                    $record,
                                    auth()->user()
                                );

                            Notification::make()
                                ->title('Meeting marked as done')
                                ->body(
                                    'Sisa slot ruangan sekarang tersedia.'
                                )
                                ->success()
                                ->send();
                        }
                    ),
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(
                        function (MeetingBooking $record): bool {
                            $user = auth()->user();

                            return $user?->can(
                                'cancel',
                                $record
                            ) === true;
                        }
                    )
                    ->form([
                        Textarea::make('reason')
                            ->label('Cancellation Reason')
                            ->rows(3)
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->action(
                        function (
                            MeetingBooking $record,
                            array $data
                        ): void {
                            app(MeetingBookingService::class)
                                ->cancel(
                                    $record,
                                    auth()->user(),
                                    $data['reason']
                                );

                            Notification::make()
                                ->title('Booking cancelled')
                                ->success()
                                ->send();
                        }
                    ),
                DeleteAction::make()
                    ->visible(
                        fn (MeetingBooking $record): bool => auth()->user()
                            ?->can('delete', $record) === true
                    ),
            ]);
    }
}
