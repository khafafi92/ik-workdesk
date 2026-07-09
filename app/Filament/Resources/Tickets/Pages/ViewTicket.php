<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(
                    fn (): bool =>
                        TicketResource::canEdit($this->record)
                ),

            DeleteAction::make()
                ->visible(
                    fn (): bool =>
                        TicketResource::canDelete($this->record)
                )
                ->before(function (): void {
                    abort_unless(
                        TicketResource::canDelete($this->record),
                        403,
                        'Service request ini tidak dapat dihapus.'
                    );
                }),
        ];
    }
}
