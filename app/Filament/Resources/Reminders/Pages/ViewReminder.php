<?php

namespace App\Filament\Resources\Reminders\Pages;

use App\Filament\Resources\Reminders\ReminderResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewReminder extends ViewRecord
{
    protected static string $resource = ReminderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markAsDone')
                ->label(__('Done'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->status !== 'done')
                ->requiresConfirmation()
                ->modalHeading(__('Mark the reminder as Done?'))
                ->modalDescription(__('This reminder will be completed and the next reminder email will not be sent.'))
                ->action(function (): void {
                    $this->record->markAsDone();

                    Notification::make()
                        ->title(__('Reminder successfully marked Done'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
