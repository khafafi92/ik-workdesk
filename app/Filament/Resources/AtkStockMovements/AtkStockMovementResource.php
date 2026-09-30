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

    protected static ?string $navigationLabel = 'Mutasi Gudang';

    protected static ?string $modelLabel = 'Mutasi Gudang ATK';

    protected static ?string $pluralModelLabel = 'Mutasi Gudang ATK';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('item.code')->label('Kode')->searchable(),
                TextColumn::make('item.name')->label('Barang ATK')->searchable()->sortable(),
                TextColumn::make('movement_type')->label('Jenis')->badge()->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title()->toString()),
                TextColumn::make('qty')->label('Jumlah')->numeric(decimalPlaces: 2),
                TextColumn::make('balance_after')->label('Saldo akhir')->numeric(decimalPlaces: 2),
                TextColumn::make('creator.name')->label('Oleh')->toggleable(),
                TextColumn::make('note')->label('Catatan')->limit(45)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('movement_type')->label('Jenis')->options([
                    'incoming' => 'Stok masuk', 'outgoing' => 'Penyerahan', 'adjustment' => 'Penyesuaian',
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
        return auth()->user()?->hasPermission('atk.manage') === true
            || auth()->user()?->hasPermission('atk.report') === true;
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
