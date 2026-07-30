<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Vehicle Information')
                ->description(
                    'Nama, nomor polisi, dan informasi kendaraan dapat diubah kapan saja.'
                )
                ->schema([
                    TextInput::make('name')
                        ->label('Vehicle Name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('plate_number')
                        ->label('Plate Number')
                        ->required()
                        ->maxLength(30)
                        ->unique(ignoreRecord: true),
                    TextInput::make('vehicle_type')
                        ->label('Vehicle Type')
                        ->placeholder('MPV, SUV, Sedan')
                        ->maxLength(100),
                    TextInput::make('brand_model')
                        ->label('Brand / Model')
                        ->maxLength(255),
                    TextInput::make('capacity')
                        ->label('Passenger Capacity')
                        ->numeric()
                        ->minValue(1)
                        ->default(5)
                        ->required(),
                    TextInput::make('color')
                        ->label('Color')
                        ->maxLength(100),
                    TimePicker::make('available_from')
                        ->label('Available From')
                        ->seconds(false)
                        ->default('06:00')
                        ->required(),
                    TimePicker::make('available_until')
                        ->label('Available Until')
                        ->seconds(false)
                        ->default('22:00')
                        ->after('available_from')
                        ->required(),
                    Textarea::make('notes')
                        ->label('Notes')
                        ->rows(3)
                        ->columnSpanFull(),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->required(),
                ])
                ->columns(2),
        ]);
    }
}
