<?php

namespace App\Filament\Resources\AttendanceResults\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class AttendanceResultForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('attendance_import_id')
                    ->numeric(),
                TextInput::make('employee_name')
                    ->required(),
                DatePicker::make('attendance_date'),
                TimePicker::make('clock_in'),
                TimePicker::make('clock_out'),
                TextInput::make('work_minutes')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('location_gps_name'),
                TextInput::make('distance_meters')
                    ->numeric(),
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
