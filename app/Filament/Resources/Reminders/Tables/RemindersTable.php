<?php

namespace App\Filament\Resources\Reminders\Tables;

use App\Filament\Resources\WorkTasks\WorkTaskResource;
use App\Models\Reminder;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
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

                TextColumn::make('workTask.task_no')
                    ->label('Source Task')
                    ->placeholder('Manual')
                    ->badge()
                    ->color('info')
                    ->url(
                        fn ($record): ?string => $record->workTask
                            ? WorkTaskResource::getUrl('view', ['record' => $record->workTask])
                            : null
                    ),

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

            ])
            ->defaultSort('reminder_at', 'asc')
            ->recordActions([
                ViewAction::make(),
                Action::make('markAsDone')
                    ->label('Done')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Reminder $record): bool => $record->status !== 'done')
                    ->requiresConfirmation()
                    ->modalHeading('Tandai reminder sebagai Done?')
                    ->modalDescription('Reminder ini akan diselesaikan dan email pengingat berikutnya tidak akan dikirim.')
                    ->action(function (Reminder $record): void {
                        $record->markAsDone();

                        Notification::make()
                            ->title('Reminder berhasil ditandai Done')
                            ->success()
                            ->send();
                    }),
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
