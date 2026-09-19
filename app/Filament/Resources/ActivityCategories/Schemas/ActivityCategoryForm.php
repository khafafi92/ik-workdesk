<?php

namespace App\Filament\Resources\ActivityCategories\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ActivityCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label('Code')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(50),
            TextInput::make('name')
                ->label('Category')
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->rows(3)
                ->columnSpanFull(),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }
}
