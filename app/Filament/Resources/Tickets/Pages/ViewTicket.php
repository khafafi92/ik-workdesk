<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use App\Livewire\TicketCollaborationRoom;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Livewire as LivewireComponent;
use Filament\Schemas\Schema;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reviseAndResubmit')
                ->label('Perbaiki & Ajukan Ulang')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->url(fn (): string => TicketResource::getUrl('edit', [
                    'record' => $this->record,
                ]))
                ->visible(
                    fn (): bool => TicketResource::canReviseRejectedLegalRequest(
                        $this->record
                    )
                ),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
            LivewireComponent::make(
                TicketCollaborationRoom::class,
                ['record' => $this->getRecord()]
            ),
        ]);
    }
}
