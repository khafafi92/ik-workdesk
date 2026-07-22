<?php

namespace App\Filament\Resources\WorkTasks\Pages;

use App\Filament\Resources\WorkTasks\WorkTaskResource;
use App\Filament\Resources\Tickets\TicketResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkTask extends ViewRecord
{
    protected static string $resource = WorkTaskResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        if (WorkTaskResource::canEdit($this->record)) {
            $this->redirect(
                WorkTaskResource::getUrl(
                    'edit',
                    ['record' => $this->record]
                )
            );
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openCollaborationRoom')
                ->label('Open Collaboration Room')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('info')
                ->url(
                    fn (): string => TicketResource::getUrl(
                        'view',
                        ['record' => $this->record->ticket_id]
                    )
                )
                ->visible(fn (): bool => filled($this->record->ticket_id)),

            Action::make('markAsDone')
                ->label('Mark as Done')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Selesaikan Work Log')
                ->modalDescription(
                    'Work Log akan ditandai selesai dan status Service Desk akan diperbarui.'
                )
                ->visible(
                    fn (): bool =>
                        $this->record->status !== 'done'
                        && $this->record->canBeCompletedBy(auth()->user())
                )
                ->action(function (): void {
                    abort_unless(
                        $this->record->canBeCompletedBy(auth()->user()),
                        403,
                        'Hanya pembuat Service Desk atau superadmin yang dapat menyelesaikan Work Log.'
                    );

                    $this->record->update(['status' => 'done']);

                    Notification::make()
                        ->title('Work Log selesai')
                        ->body('Status Service Desk telah diperbarui.')
                        ->success()
                        ->send();

                    $this->refreshFormData([
                        'status',
                        'progress_percent',
                        'completed_at',
                    ]);
                }),

            EditAction::make()
                ->visible(
                    fn (): bool =>
                        WorkTaskResource::canEdit($this->record)
                ),

            DeleteAction::make()
                ->visible(
                    fn (): bool =>
                        WorkTaskResource::canDelete($this->record)
                )
                ->before(function (): void {
                    abort_unless(
                        WorkTaskResource::canDelete($this->record),
                        403,
                        'Work log ini tidak dapat dihapus.'
                    );
                }),
        ];
    }
}
