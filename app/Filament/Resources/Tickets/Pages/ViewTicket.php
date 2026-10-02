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
                ->label('Jadikan kolaboratif')
                ->icon('heroicon-o-user-group')
                ->color('primary')
                ->modalHeading('Jadikan Service Desk kolaboratif')
                ->modalDescription('Work Log utama tetap berjalan. Sistem akan membuat Work Log baru untuk setiap departemen tambahan yang dipilih.')
                ->form([
                    Select::make('department_ids')
                        ->label('Departemen tambahan')
                        ->options(fn (): array => app(TicketCollaborationService::class)->additionalDepartmentOptions($this->record))
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('Pilih departemen yang perlu ikut mengerjakan atau melakukan review.'),
                ])
                ->modalSubmitActionLabel('Tambahkan ke kolaborasi')
                ->visible(fn (): bool => TicketResource::canPromoteToCollaborative($this->record))
                ->action(function (array $data): void {
                    $ticket = app(TicketCollaborationService::class)->promote(
                        $this->record,
                        $data['department_ids']
                    );

                    $this->record = $ticket;

                    Notification::make()
                        ->title('Service Desk sudah menjadi kolaboratif')
                        ->body('Work Log utama dipertahankan dan departemen tambahan telah menerima Work Log baru.')
                        ->success()
                        ->send();
                }),
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
