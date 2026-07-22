<?php

namespace App\Filament\Resources\WorkTasks\Pages;

use App\Filament\Resources\WorkTasks\WorkTaskResource;
use App\Filament\Resources\Tickets\TicketResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkTask extends EditRecord
{
    protected static string $resource = WorkTaskResource::class;

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
