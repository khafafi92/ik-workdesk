<?php

namespace App\Filament\Resources\MeetingRooms\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MeetingRoomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Room')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable(),
                TextColumn::make('location')
                    ->label('Location')
                    ->placeholder('-'),
                TextColumn::make('capacity')
                    ->label('Capacity')
                    ->suffix(' people')
                    ->sortable(),
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
                    ->counts('bookings')
                    ->sortable(),
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
