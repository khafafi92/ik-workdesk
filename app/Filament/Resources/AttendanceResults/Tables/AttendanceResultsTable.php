<?php

namespace App\Filament\Resources\AttendanceResults\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttendanceResultsTable
{
    public static function configure(Table $table): Table
    {
        $translateStatus = static fn (?string $state): ?string => __($state);

        return $table
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([
                10,
                25,
                50,
                100,
            ])
            ->searchOnBlur()
            ->defaultSort('attendance_date', 'desc')
            ->columns([
                TextColumn::make('attendance_import_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('employee_code')
                    ->label(__('Employee ID'))
                    ->searchable(),
                TextColumn::make('employee_name')
                    ->label(__('Full Name'))
                    ->searchable(),
                TextColumn::make('attendance_date')
                    ->label(__('Date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('clock_in')
                    ->label('Clock In')
                    ->time()
                    ->sortable(),
                TextColumn::make('clock_out')
                    ->label('Clock Out')
                    ->time()
                    ->sortable(),
                TextColumn::make('work_minutes')
                    ->label('Work Minutes')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('location_gps_name')
                    ->label(__('Location'))
                    ->searchable(),
                TextColumn::make('expected_checkout')
                    ->time()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('location_check')
                    ->label(__('Location Check'))
                    ->formatStateUsing($translateStatus)
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'Sesuai' => 'success',
                        'Tidak Sesuai' => 'danger',
                        default => 'warning',
                    })
                    ->searchable(),
                TextColumn::make('duration_text')
                    ->label(__('Time Check'))
                    ->searchable(),
                TextColumn::make('checkout_check')
                    ->label(__('Clock-out Check'))
                    ->formatStateUsing($translateStatus)
                    ->badge()
                    ->color(fn (?string $state): string => str_contains((string) $state, '19:00 UP') || str_contains((string) $state, 'Diatas 19:00') ? 'danger' : 'success')
                    ->searchable(),
                TextColumn::make('clock_in_status')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('location_status')
                    ->formatStateUsing($translateStatus)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('checkout_status')
                    ->formatStateUsing($translateStatus)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('work_hour_status')
                    ->formatStateUsing($translateStatus)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('final_status')
                    ->label('Final Status')
                    ->formatStateUsing($translateStatus)
                    ->badge()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
