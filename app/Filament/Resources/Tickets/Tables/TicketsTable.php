<?php

namespace App\Filament\Resources\Tickets\Tables;

use App\Filament\Resources\Tickets\TicketResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([
                10,
                25,
                50,
                100,
            ])
            ->searchOnBlur()
            ->columns([
                TextColumn::make('ticket_no')
                    ->label('Request No')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('subject')
                    ->label('Subject')
                    ->searchable()
                    ->limit(35)
                    ->toggleable(),

                TextColumn::make('employee.name')
                    ->label('Requester')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('requesterDepartment.name')
                    ->label('From Dept')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('handlerDepartment.name')
                    ->label('To Dept')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('permitCompany.name')
                    ->label('Permit')
                    ->badge()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('permitKbli.code')
                    ->label('KBLI')
                    ->placeholder(fn ($record): string => $record->permit_kbli_unavailable
                        ? 'Belum terdaftar'
                        : '-')
                    ->toggleable(),

                TextColumn::make('priority')
                    ->label('Priority')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'low' => 'gray',
                        'medium' => 'info',
                        'high' => 'warning',
                        'urgent' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'open' => 'danger',
                        'in_progress' => 'warning',
                        'waiting_user' => 'info',
                        'discussion' => 'warning',
                        'resolved' => 'success',
                        'closed' => 'gray',
                        'cancel' => 'gray',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),

                TextColumn::make('resolution_notes')
                    ->label('Decision / Resolution')
                    ->placeholder('-')
                    ->wrap()
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('workTasks.employee.name')
                    ->label('Assigned To')
                    ->placeholder('Belum ditentukan')
                    ->separator(', ')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('reported_at')
                    ->label('Reported At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('due_at')
                    ->label('Due At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('resolved_at')
                    ->label('Resolved At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),

                EditAction::make()
                    ->visible(
                        fn ($record): bool => TicketResource::canEdit($record)
                    ),

                DeleteAction::make()
                    ->visible(
                        fn ($record): bool => TicketResource::canDelete($record)
                    )
                    ->before(function ($record): void {
                        abort_unless(
                            TicketResource::canDelete($record),
                            403,
                            'Service request ini tidak dapat dihapus.'
                        );
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(
                            fn (): bool => TicketResource::canDeleteAny()
                        )
                        ->before(function (): void {
                            abort_unless(
                                TicketResource::canDeleteAny(),
                                403,
                                'Anda tidak memiliki izin menghapus service request.'
                            );
                        }),
                ]),
            ]);
    }
}
