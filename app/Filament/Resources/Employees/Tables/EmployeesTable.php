<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Services\EmployeeHierarchy;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('department.name')
                    ->label(__('Department'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('permitCompanies.name')
                    ->label(__('Companies'))
                    ->placeholder('-')
                    ->listWithLineBreaks()
                    ->searchable(),
                TextColumn::make('employee_no')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('Email address'))
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('position')
                    ->searchable(),
                TextColumn::make('position_level')
                    ->label(__('Job Level'))
                    ->formatStateUsing(fn (string $state): string => EmployeeHierarchy::levels()[$state] ?? $state)
                    ->placeholder(__('Unmapped'))
                    ->sortable(),
                TextColumn::make('supervisor.name')
                    ->label(__('Direct Top'))
                    ->placeholder(__('Unmapped'))
                    ->searchable(),
                IconColumn::make('is_active')
                    ->boolean(),
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
