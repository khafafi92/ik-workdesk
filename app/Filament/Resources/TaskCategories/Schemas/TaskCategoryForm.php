<?php

namespace App\Filament\Resources\TaskCategories\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TaskCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),

                TextInput::make('name')
                    ->label('Category Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('code')
                    ->label('Code')
                    ->maxLength(50),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
