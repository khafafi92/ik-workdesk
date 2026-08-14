<?php

namespace App\Filament\Resources\WorkProjects\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WorkProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->label('Code')->searchable()->sortable(),
            TextColumn::make('name')->label('Project')->searchable()->sortable(),
            TextColumn::make('company_name')->label('Company / Client')->placeholder('-')->searchable(),
            TextColumn::make('department.name')->label('Division')->placeholder('-')->sortable(),
            TextColumn::make('manager.name')->label('Project Manager')->placeholder('-')->searchable(),
            TextColumn::make('status')->badge()->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title())
                ->color(fn (string $state): string => match ($state) {
                    'active' => 'success', 'on_hold' => 'warning', 'completed' => 'info', default => 'gray',
                }),
            TextColumn::make('start_date')->date('d M Y')->placeholder('-'),
            TextColumn::make('end_date')->date('d M Y')->placeholder('-'),
        ])->filters([
            SelectFilter::make('status')->options([
                'active' => 'Active', 'on_hold' => 'On Hold', 'completed' => 'Completed', 'cancelled' => 'Cancelled',
            ]),
        ])->defaultSort('name')->recordActions([EditAction::make()]);
    }
}
