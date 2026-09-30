<?php

namespace App\Filament\Resources\AtkCategories;

use App\Filament\Resources\AtkCategories\Pages\CreateAtkCategory;
use App\Filament\Resources\AtkCategories\Pages\EditAtkCategory;
use App\Filament\Resources\AtkCategories\Pages\ListAtkCategories;
use App\Models\AtkCategory;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AtkCategoryResource extends Resource
{
    protected static ?string $model = AtkCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Kategori Barang';

    protected static ?string $modelLabel = 'Kategori ATK';

    protected static ?string $pluralModelLabel = 'Kategori ATK';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama kategori')->required()->maxLength(255)->unique(ignoreRecord: true),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Kategori')->searchable()->sortable(),
            TextColumn::make('items_count')->label('Barang')->counts('items')->alignCenter(),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->defaultSort('name')->recordActions([EditAction::make()]);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('atk.manage') === true;
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
