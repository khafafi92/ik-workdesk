<?php

namespace App\Filament\Resources\WorkTasks\Tables;

use App\Filament\Resources\WorkTasks\WorkTaskResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
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

                TextColumn::make('employee.name')
                    ->label('PIC / Pelaksana')
                    ->placeholder('Belum ditentukan')
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

                TextColumn::make('approval_status')
                    ->label('Legal Approval')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pending' => 'Menunggu CBO',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        default => 'Tidak diperlukan',
                    })
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),

                TextColumn::make('rejection_reason')
                    ->label('Rejection Reason')
                    ->placeholder('-')
                    ->wrap()
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),

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

                TextColumn::make('completedBy.name')
                    ->label('Marked Done By')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('approveLegalTask')
                    ->label('Approve Legal')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve task Legal?')
                    ->modalDescription('Setelah di-approve, task akan muncul dan dapat dikerjakan oleh divisi Legal.')
                    ->visible(
                        fn ($record): bool => $record->canBeApprovedBy(auth()->user())
                    )
                    ->action(function ($record): void {
                        $record->approveLegalTask(auth()->user());

                        Notification::make()
                            ->title('Task Legal berhasil di-approve')
                            ->body('Task sekarang sudah muncul di divisi Legal.')
                            ->success()
                            ->send();
                    }),

                Action::make('rejectLegalTask')
                    ->label('Reject Legal')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reject task Legal?')
                    ->modalDescription('Alasan penolakan wajib diisi dan akan terlihat oleh requester serta Legal.')
                    ->schema([
                        Textarea::make('rejection_reason')
                            ->label('Alasan Penolakan')
                            ->required()
                            ->rows(4)
                            ->maxLength(2000),
                    ])
                    ->visible(
                        fn ($record): bool => $record->canBeApprovedBy(auth()->user())
                    )
                    ->action(function ($record, array $data): void {
                        $record->rejectLegalTask(
                            auth()->user(),
                            $data['rejection_reason']
                        );

                        Notification::make()
                            ->title('Task Legal telah ditolak')
                            ->body('Keputusan dan alasan penolakan sudah dicatat.')
                            ->danger()
                            ->send();
                    }),

                ViewAction::make(),

                EditAction::make()
                    ->visible(
                        fn ($record): bool => WorkTaskResource::canEdit($record)
                    ),

                DeleteAction::make()
                    ->visible(
                        fn ($record): bool => WorkTaskResource::canDelete($record)
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
                            fn (): bool => WorkTaskResource::canDeleteAny()
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
