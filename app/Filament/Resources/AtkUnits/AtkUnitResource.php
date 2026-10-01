<?php

namespace App\Filament\Resources\AtkUnits;

use App\Filament\Resources\AtkUnits\Pages\CreateAtkUnit;
use App\Filament\Resources\AtkUnits\Pages\EditAtkUnit;
use App\Filament\Resources\AtkUnits\Pages\ListAtkUnits;
use App\Models\AtkUnit;
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

class AtkUnitResource extends Resource
{
    protected static ?string $model = AtkUnit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $navigationLabel = 'Satuan Barang';

    protected static ?string $modelLabel = 'Satuan ATK';

    protected static ?string $pluralModelLabel = 'Satuan ATK';

    protected static ?int $navigationSort = 7;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama satuan')->required()->maxLength(50)->unique(ignoreRecord: true),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Satuan')->searchable()->sortable(),
            TextColumn::make('items_count')->label('Barang')->counts('items')->alignCenter(),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->defaultSort('name')->recordActions([EditAction::make()]);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('atk.units') === true;
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
            'index' => ListAtkUnits::route('/'),
            'create' => CreateAtkUnit::route('/create'),
            'edit' => EditAtkUnit::route('/{record}/edit'),
        ];
    }
}
