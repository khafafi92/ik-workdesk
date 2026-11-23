<?php

namespace App\Filament\Resources\AtkStockMovements;

use App\Filament\Resources\AtkStockMovements\Pages\ListAtkStockMovements;
use App\Models\AtkStockMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AtkStockMovementResource extends Resource
{
    protected static ?string $model = AtkStockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationLabel = 'Office Supply Stock Movements';

    protected static ?string $modelLabel = 'Office Supply Stock Movements';

    protected static ?string $pluralModelLabel = 'Office Supply Stock Movements';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Time')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('item.code')->label('Code')->searchable(),
                TextColumn::make('item.name')->label('Office Supply Items')->searchable()->sortable(),
                TextColumn::make('movement_type')->label('Type')->badge()->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title()->toString()),
                TextColumn::make('qty')->label('Quantity')->numeric(decimalPlaces: 0),
                TextColumn::make('balance_after')->label('End balance')->numeric(decimalPlaces: 0),
                TextColumn::make('creator.name')->label('By')->toggleable(),
                TextColumn::make('note')->label('Notes')->limit(45)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('movement_type')->label('Type')->options([
                    'incoming' => 'Stock in', 'outgoing' => 'Issue', 'adjustment' => 'Adjustment',
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['item', 'creator']);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('atk.stock-movement') === true;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'ATK';
    }

    public static function getPages(): array
    {
        return ['index' => ListAtkStockMovements::route('/')];
    }
}
