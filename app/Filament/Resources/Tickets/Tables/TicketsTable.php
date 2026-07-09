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
                    ->sortable(),

                TextColumn::make('subject')
                    ->label('Subject')
                    ->searchable()
                    ->limit(35),

                TextColumn::make('employee.name')
                    ->label('Requester')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('requesterDepartment.name')
                    ->label('From Dept')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('handlerDepartment.name')
                    ->label('To Dept')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('priority')
                    ->label('Priority')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'low' => 'gray',
                        'medium' => 'info',
                        'high' => 'warning',
                        'urgent' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'open' => 'danger',
                        'in_progress' => 'warning',
                        'waiting_user' => 'info',
                        'resolved' => 'success',
                        'closed' => 'gray',
                        'cancel' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('assigned_to')
                    ->label('Assigned To')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('reported_at')
                    ->label('Reported At')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

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
                        fn ($record): bool =>
                            TicketResource::canEdit($record)
                    ),

                DeleteAction::make()
                    ->visible(
                        fn ($record): bool =>
                            TicketResource::canDelete($record)
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
                            fn (): bool =>
                                TicketResource::canDeleteAny()
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
