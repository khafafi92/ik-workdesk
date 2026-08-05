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
                ->label('Done')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->status !== 'done')
                ->requiresConfirmation()
                ->modalHeading('Tandai reminder sebagai Done?')
                ->modalDescription('Reminder ini akan diselesaikan dan email pengingat berikutnya tidak akan dikirim.')
                ->action(function (): void {
                    $this->record->markAsDone();

                    Notification::make()
                        ->title('Reminder berhasil ditandai Done')
                        ->success()
                        ->send();
                }),
        ];
    }
}
