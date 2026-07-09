<?php

namespace App\Filament\Resources\WorkLocations\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class WorkLocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('gps_name')
                    ->required(),
                Textarea::make('address')
                    ->columnSpanFull(),
                TextInput::make('latitude')
                    ->numeric(),
                TextInput::make('longitude')
                    ->numeric(),
                TextInput::make('radius_meters')
                    ->required()
                    ->numeric()
                    ->default(50),
                Toggle::make('is_flexible')
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
