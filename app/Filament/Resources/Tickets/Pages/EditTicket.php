<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTicket extends EditRecord
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
