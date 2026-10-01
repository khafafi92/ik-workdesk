<?php

namespace App\Filament\Resources\Departments\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DepartmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->required(),
                Toggle::make('requires_cbo_approval')
                    ->label('Wajib persetujuan CBO')
                    ->helperText('Task yang ditujukan ke department ini menunggu persetujuan CBO sebelum dapat dikerjakan.'),
            ]);
    }
}
