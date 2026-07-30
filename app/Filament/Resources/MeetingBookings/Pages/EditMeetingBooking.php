<?php

namespace App\Filament\Resources\MeetingBookings\Pages;

use App\Filament\Resources\MeetingBookings\MeetingBookingResource;
use App\Services\MeetingBookingService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class EditMeetingBooking extends EditRecord
{
    protected static string $resource = MeetingBookingResource::class;

    protected function mutateFormDataBeforeFill(
        array $data
    ): array {
        $start = Carbon::parse($data['start_at']);
        $end = Carbon::parse($data['end_at']);

        $data['meeting_date'] = $start->toDateString();
        $data['start_time'] = $start->format('H:i');
        $data['duration_hours'] = (string) (
            $start->diffInMinutes($end) / 60
        );

        return $data;
    }

    protected function mutateFormDataBeforeSave(
        array $data
    ): array {
        return app(MeetingBookingService::class)
            ->normalizeScheduleData($data);
    }

    protected function handleRecordUpdate(
        Model $record,
        array $data
    ): Model {
        return app(MeetingBookingService::class)
            ->update($record, $data);
    }

    protected function getRedirectUrl(): string
    {
        return MeetingBookingResource::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('complete')
                ->label('Set Done')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(
                    fn (): bool =>
                        $this->record->canBeCompleted()
                )
                ->requiresConfirmation()
                ->modalHeading('Finish this meeting?')
                ->modalDescription(
                    'Meeting akan ditandai selesai dan sisa jadwal ruangan langsung tersedia.'
                )
                ->action(function (): void {
                    app(MeetingBookingService::class)
                        ->complete(
                            $this->record,
                            auth()->user()
                        );

                    Notification::make()
                        ->title('Meeting marked as done')
                        ->success()
                        ->send();

                    $this->redirect(
                        MeetingBookingResource::getUrl('index'),
                        navigate: true
                    );
                }),
        ];
    }
}
