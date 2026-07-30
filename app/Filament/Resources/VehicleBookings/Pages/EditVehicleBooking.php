<?php

namespace App\Filament\Resources\VehicleBookings\Pages;

use App\Filament\Resources\VehicleBookings\VehicleBookingResource;
use App\Services\VehicleBookingService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class EditVehicleBooking extends EditRecord
{
    protected static string $resource =
        VehicleBookingResource::class;

    protected function mutateFormDataBeforeFill(
        array $data
    ): array {
        $start = Carbon::parse($data['start_at']);
        $end = Carbon::parse($data['end_at']);
        $data['booking_date'] = $start->toDateString();
        $data['start_time'] = $start->format('H:i');
        $data['duration_hours'] = (string) (
            $start->diffInMinutes($end) / 60
        );

        return $data;
    }

    protected function mutateFormDataBeforeSave(
        array $data
    ): array {
        return app(VehicleBookingService::class)
            ->normalizeScheduleData($data);
    }

    protected function handleRecordUpdate(
        Model $record,
        array $data
    ): Model {
        return app(VehicleBookingService::class)
            ->update($record, $data);
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
                ->action(function (): void {
                    app(VehicleBookingService::class)
                        ->complete(
                            $this->record,
                            auth()->user()
                        );
                    Notification::make()
                        ->title('Trip marked as done')
                        ->success()
                        ->send();
                    $this->redirect(
                        VehicleBookingResource::getUrl('index'),
                        navigate: true
                    );
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return VehicleBookingResource::getUrl('index');
    }
}
