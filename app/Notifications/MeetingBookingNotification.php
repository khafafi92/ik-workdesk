<?php

namespace App\Notifications;

use App\Filament\Resources\MeetingBookings\MeetingBookingResource;
use App\Models\MeetingBooking;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MeetingBookingNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly MeetingBooking $booking,
        public readonly string $event = 'created'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $booking = $this->booking->loadMissing([
            'room',
            'organizer',
        ]);
        $cancelled = $this->event === 'cancelled';

        return FilamentNotification::make()
            ->title(
                $cancelled
                    ? 'Meeting dibatalkan'
                    : 'Undangan meeting baru'
            )
            ->body(sprintf(
                '%s · %s · %s–%s · %s',
                $booking->title,
                $booking->room?->name ?? '-',
                $booking->start_at->format('d M Y H:i'),
                $booking->end_at->format('H:i'),
                $booking->organizer?->name ?? '-'
            ))
            ->icon(
                $cancelled
                    ? 'heroicon-o-x-circle'
                    : 'heroicon-o-calendar-days'
            )
            ->color($cancelled ? 'danger' : 'info')
            ->actions([
                Action::make('open')
                    ->label('Lihat Booking')
                    ->button()
                    ->url(
                        MeetingBookingResource::getUrl('index')
                    )
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
