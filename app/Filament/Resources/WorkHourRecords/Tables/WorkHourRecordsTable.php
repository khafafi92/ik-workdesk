<?php

namespace App\Filament\Resources\WorkHourRecords\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkHourRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('import.period_name')
                    ->label('Period')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('raw_data.employee_id')
                    ->label('Employee ID'),

                TextColumn::make('employee_name')
                    ->label('Full Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('work_hours_text')
                    ->label('Total Jam Kerja')
                    ->sortable(),

                TextColumn::make('work_minutes')
                    ->label('Total Menit')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('raw_data.period_name')
                    ->label('Sheet Period')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('employee_name')
            ->filters([
                //
            ]);
    }
}
