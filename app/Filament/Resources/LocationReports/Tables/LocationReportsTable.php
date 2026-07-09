<?php

namespace App\Filament\Resources\LocationReports\Tables;

use App\Filament\Resources\AttendanceImports\AttendanceImportResource;
use App\Services\AttendanceReportProcessor;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LocationReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('period_name')
                    ->label('Periode')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('attendance_file_path')
                    ->label('File Lokasi')
                    ->limit(35),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'draft' => 'gray',
                        'uploaded' => 'info',
                        'processed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('processed_at')
                    ->label('Diproses')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Diupload')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('view_results')
                    ->label('Lihat Hasil')
                    ->icon('heroicon-o-table-cells')
                    ->color('info')
                    ->url(fn ($record) => AttendanceImportResource::getUrl('results', ['record' => $record])),

                Action::make('process')
                    ->label('Proses Lokasi')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        app(AttendanceReportProcessor::class)->process($record);

                        Notification::make()
                            ->title('Laporan lokasi berhasil diproses')
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
