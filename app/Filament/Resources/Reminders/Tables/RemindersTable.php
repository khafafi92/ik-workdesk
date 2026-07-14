<?php

namespace App\Filament\Resources\Reminders\Tables;
use Filament\Actions\ViewAction;

use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;

use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RemindersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reminder_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'meeting' => 'Meeting',
                        'task' => 'Task',
                        'service_request' => 'Service Desk',
                        'report' => 'Report',
                        'general' => 'General',
                        default => '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'meeting' => 'info',
                        'task' => 'warning',
                        'service_request' => 'danger',
                        'report' => 'success',
                        'general' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                TextColumn::make('employee.name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('department.name')
                    ->label('Department')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('reminder_at')
                    ->label('Reminder At')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'done' => 'success',
                        'cancel' => 'gray',
                        default => 'gray',
                    }),

                IconColumn::make('is_notified')
                    ->label('Notified')
                    ->boolean(),
            ])
            ->defaultSort('reminder_at', 'asc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
