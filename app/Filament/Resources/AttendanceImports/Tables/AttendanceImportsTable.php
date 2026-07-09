<?php

namespace App\Filament\Resources\AttendanceImports\Tables;

use App\Filament\Resources\AttendanceImports\AttendanceImportResource;
use App\Models\AttendanceImport;
use App\Services\AttendanceReportProcessor;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttendanceImportsTable
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
                TextColumn::make('period_name')
                    ->label('Period')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('attendance_file_name')
                    ->label('Attendance File')
                    ->limit(30)
                    ->tooltip(
                        fn (?string $state): ?string => $state
                    )
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                TextColumn::make('work_hour_file_name')
                    ->label('Work Hour File')
                    ->limit(30)
                    ->tooltip(
                        fn (?string $state): ?string => $state
                    )
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(
                        fn (?string $state): string => match ($state) {
                            'draft' => 'gray',
                            'uploaded' => 'info',
                            'processing' => 'warning',
                            'processed' => 'success',
                            'failed' => 'danger',
                            default => 'gray',
                        }
                    ),

                TextColumn::make('processed_at')
                    ->label('Processed At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Uploaded At')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('view_results')
                    ->label('View Results')
                    ->icon('heroicon-o-table-cells')
                    ->color('info')
                    ->visible(
                        fn (): bool =>
                            auth()->user()
                                ?->hasPermission('attendance.view') === true
                    )
                    ->before(function (): void {
                        abort_unless(
                            auth()->user()
                                ?->hasPermission('attendance.view') === true,
                            403,
                            'Anda tidak memiliki izin melihat attendance.'
                        );
                    })
                    ->url(
                        fn (AttendanceImport $record): string =>
                            AttendanceImportResource::getUrl(
                                'results',
                                ['record' => $record]
                            )
                    ),

                Action::make('process')
                    ->label('Process Report')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(
                        fn (): bool =>
                            auth()->user()
                                ?->hasPermission('attendance.manage') === true
                    )
                    ->before(function (): void {
                        abort_unless(
                            auth()->user()
                                ?->hasPermission('attendance.manage') === true,
                            403,
                            'Anda tidak memiliki izin memproses attendance.'
                        );
                    })
                    ->action(
                        function (AttendanceImport $record): void {
                            app(AttendanceReportProcessor::class)
                                ->process($record);

                            Notification::make()
                                ->title('Attendance report processed')
                                ->body(
                                    'Data Excel berhasil diproses ke Attendance Results.'
                                )
                                ->success()
                                ->send();
                        }
                    ),

                EditAction::make()
                    ->visible(
                        fn (): bool =>
                            auth()->user()
                                ?->hasPermission('attendance.manage') === true
                    )
                    ->before(function (): void {
                        abort_unless(
                            auth()->user()
                                ?->hasPermission('attendance.manage') === true,
                            403
                        );
                    }),

                DeleteAction::make()
                    ->visible(
                        fn (): bool =>
                            auth()->user()
                                ?->hasPermission('attendance.manage') === true
                    )
                    ->before(function (): void {
                        abort_unless(
                            auth()->user()
                                ?->hasPermission('attendance.manage') === true,
                            403
                        );
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(
                            fn (): bool =>
                                auth()->user()
                                    ?->hasPermission(
                                        'attendance.manage'
                                    ) === true
                        )
                        ->before(function (): void {
                            abort_unless(
                                auth()->user()
                                    ?->hasPermission(
                                        'attendance.manage'
                                    ) === true,
                                403
                            );
                        }),
                ]),
            ]);
    }
}