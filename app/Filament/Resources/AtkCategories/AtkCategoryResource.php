<?php

namespace App\Filament\Resources\AtkCategories;

use App\Filament\LocalizedResource as Resource;
use App\Filament\Resources\AtkCategories\Pages\CreateAtkCategory;
use App\Filament\Resources\AtkCategories\Pages\EditAtkCategory;
use App\Filament\Resources\AtkCategories\Pages\ListAtkCategories;
use App\Models\AtkCategory;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AtkCategoryResource extends Resource
{
    protected static ?string $model = AtkCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Item Categories';

    protected static ?string $modelLabel = 'Item Category';

    protected static ?string $pluralModelLabel = 'Item Categories';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('Category name'))->required()->maxLength(255)->unique(ignoreRecord: true),
            Toggle::make('is_active')->label(__('Active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('Category'))->searchable()->sortable(),
            TextColumn::make('items_count')->label(__('Item'))->counts('items')->alignCenter(),
            IconColumn::make('is_active')->label(__('Active'))->boolean(),
        ])->defaultSort('name')->recordActions([EditAction::make()]);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('atk.categories') === true;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationGroup(): ?string
    {
        return 'ATK';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAtkCategories::route('/'),
            'create' => CreateAtkCategory::route('/create'),
            'edit' => EditAtkCategory::route('/{record}/edit'),
        ];
    }
}
