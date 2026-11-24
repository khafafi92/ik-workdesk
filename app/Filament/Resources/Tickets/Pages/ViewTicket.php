<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use App\Livewire\TicketCollaborationRoom;
use App\Services\TicketCollaborationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Livewire as LivewireComponent;
use Filament\Schemas\Schema;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('promoteToCollaborative')
                ->label(__('Enable collaboration'))
                ->icon('heroicon-o-user-group')
                ->color('primary')
                ->modalHeading(__('Make Service Desk collaborative'))
                ->modalDescription(__('Main Log work is running. System will create a new Log Work for each selected additional department.'))
                ->form([
                    Select::make('department_ids')
                        ->label(__('Extra department'))
                        ->options(fn (): array => app(TicketCollaborationService::class)->additionalDepartmentOptions($this->record))
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText(__('Choose a department that needs to come along and do reviews.')),
                ])
                ->modalSubmitActionLabel(__('Add to collaboration'))
                ->visible(fn (): bool => TicketResource::canPromoteToCollaborative($this->record))
                ->action(function (array $data): void {
                    $ticket = app(TicketCollaborationService::class)->promote(
                        $this->record,
                        $data['department_ids']
                    );

                    $this->record = $ticket;

                    Notification::make()
                        ->title(__('Service Desk has become collaborative'))
                        ->body(__('Main Log work is maintained and additional departments have received a new Log Work.'))
                        ->success()
                        ->send();
                }),
            Action::make('reviseAndResubmit')
                ->label(__('Fix & Resubmit'))
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
