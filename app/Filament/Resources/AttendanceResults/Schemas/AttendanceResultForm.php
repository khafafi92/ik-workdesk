<?php

namespace App\Filament\Resources\AttendanceResults\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class AttendanceResultForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('attendance_import_id')
                    ->label('Period')
                    ->relationship('import', 'period_name')
                    ->searchable()
                    ->preload()
                    ->exists('attendance_imports', 'id'),
                TextInput::make('employee_name')
                    ->required()
                    ->maxLength(255),
                DatePicker::make('attendance_date'),
                TimePicker::make('clock_in'),
                TimePicker::make('clock_out'),
                TextInput::make('work_minutes')
                    ->required()
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(2147483647)
                    ->default(0),
                TextInput::make('location_gps_name'),
                TextInput::make('distance_meters')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(99999999.99),
                TimePicker::make('expected_checkout'),
                TextInput::make('clock_in_status'),
                TextInput::make('location_status'),
                TextInput::make('checkout_status'),
                TextInput::make('work_hour_status'),
                TextInput::make('final_status'),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
