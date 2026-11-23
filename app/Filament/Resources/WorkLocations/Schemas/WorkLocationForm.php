<?php

namespace App\Filament\Resources\WorkLocations\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class WorkLocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('gps_name')
                    ->label('Location Group Name')
                    ->helperText('For some points in the same location, use the same name, such as the KPMOG Office.')
                    ->maxLength(255)
                    ->required(),
                Textarea::make('address')
                    ->label('Point Description')
                    ->helperText('Example: front door, main building, parking, or back door.')
                    ->columnSpanFull(),
                TextInput::make('latitude')
                    ->label('Latitude')
                    ->helperText('Example: -6,2849340')
                    ->numeric()
                    ->minValue(-90)
                    ->maxValue(90)
                    ->required(),
                TextInput::make('longitude')
                    ->label('Longitude')
                    ->helperText('Example: 106,8069252')
                    ->numeric()
                    ->minValue(-180)
                    ->maxValue(180)
                    ->required(),
                TextInput::make('radius_meters')
                    ->label('Radius (meter)')
                    ->helperText('Use 1005 feet [30 considered m] early; adjust it according to GPS results in the field.')
                    ->required()
                    ->numeric()
                    ->minValue(5)
                    ->maxValue(1000)
                    ->default(50),
                Toggle::make('is_flexible')
                    ->label('Flexible Location')
                    ->helperText('Enable only if absenteeship is permissible from any location.')
                    ->default(false),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
