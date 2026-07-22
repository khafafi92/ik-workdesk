<?php

namespace App\Filament\Resources\WorkTasks\Tables;

use App\Filament\Resources\WorkTasks\WorkTaskResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkTasksTable
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
                TextColumn::make('task_no')
                    ->label('Task No')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('ticket.ticket_no')
                    ->label('Request No')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('ticket.subject')
                    ->label('Request Subject')
                    ->searchable()
                    ->limit(35),

                TextColumn::make('ticket.employee.name')
                    ->label('Requester')
                    ->searchable(),

                TextColumn::make('ticket.requesterDepartment.name')
                    ->label('From Dept')
                    ->searchable(),

                TextColumn::make('department.name')
                    ->label('To Dept')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('ticket.category.name')
                    ->label('Request Category')
                    ->searchable(),

                // TextColumn::make('category.name')
                //     ->label('Task Category')
                //     ->searchable()
                //     ->placeholder('-')
                //     ->toggleable(),

                TextColumn::make('employee.name')
                    ->label('PIC')
                    ->searchable(),

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
                    ->label('Work Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'planned' => 'gray',
                        'in_progress' => 'warning',
                        'done' => 'success',
                        'hold' => 'info',
                        'cancel' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('progress_percent')
                    ->label('Progress')
                    ->suffix('%')
                    ->sortable(),

                TextColumn::make('due_at')
                    ->label('Due At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('completed_at')
                    ->label('Completed At')
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
                            WorkTaskResource::canEdit($record)
                    ),

                DeleteAction::make()
                    ->visible(
                        fn ($record): bool =>
                            WorkTaskResource::canDelete($record)
                    )
                    ->before(function ($record): void {
                        abort_unless(
                            WorkTaskResource::canDelete($record),
                            403,
                            'Work log ini tidak dapat dihapus.'
                        );
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(
                            fn (): bool =>
                                WorkTaskResource::canDeleteAny()
                        )
                        ->before(function (): void {
                            abort_unless(
                                WorkTaskResource::canDeleteAny(),
                                403,
                                'Anda tidak memiliki izin menghapus work log.'
                            );
                        }),
                ]),
            ]);
    }
}
