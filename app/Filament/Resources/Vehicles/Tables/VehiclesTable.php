<?php

namespace App\Filament\Resources\Vehicles\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VehiclesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Vehicle')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('plate_number')
                    ->label('Plate Number')
                    ->badge()
                    ->searchable(),
                TextColumn::make('brand_model')
                    ->label('Brand / Model')
                    ->placeholder('-'),
                TextColumn::make('capacity')
                    ->label('Capacity')
                    ->suffix(' people'),
                TextColumn::make('available_from')
                    ->label('Available')
                    ->formatStateUsing(
                        fn ($record): string => substr($record->available_from, 0, 5)
                            .'–'
                            .substr($record->available_until, 0, 5)
                    ),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('bookings_count')
                    ->label('Bookings')
                    ->counts('bookings'),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(
                        fn ($record): bool => auth()->user()
                            ?->can('delete', $record) === true
                    ),
            ]);
    }
}
