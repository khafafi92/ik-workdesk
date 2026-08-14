<?php

namespace App\Filament\Resources\ActivityCategories;

use App\Filament\Resources\ActivityCategories\Pages\CreateActivityCategory;
use App\Filament\Resources\ActivityCategories\Pages\EditActivityCategory;
use App\Filament\Resources\ActivityCategories\Pages\ListActivityCategories;
use App\Filament\Resources\Concerns\AdminOnlyResource;
use App\Models\ActivityCategory;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ActivityCategoryResource extends Resource
{
    use AdminOnlyResource;

    protected static ?string $model = ActivityCategory::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Activity Categories';

    protected static ?string $modelLabel = 'Activity Category';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('Code')->required()->unique(ignoreRecord: true)->maxLength(50),
            TextInput::make('name')->label('Category')->required()->maxLength(255),
            Textarea::make('description')->rows(3)->columnSpanFull(),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->label('Code')->searchable()->sortable(),
            TextColumn::make('name')->label('Category')->searchable()->sortable(),
            TextColumn::make('description')->limit(60)->wrap(),
            IconColumn::make('is_active')->label('Active')->boolean(),
        ])->defaultSort('name')->recordActions([EditAction::make()]);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityCategories::route('/'),
            'create' => CreateActivityCategory::route('/create'),
            'edit' => EditActivityCategory::route('/{record}/edit'),
        ];
    }
}
