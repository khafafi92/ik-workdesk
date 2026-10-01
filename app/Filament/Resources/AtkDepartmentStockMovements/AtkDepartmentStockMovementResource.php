<?php

namespace App\Filament\Resources\AtkDepartmentStockMovements;

use App\Filament\Resources\AtkDepartmentStockMovements\Pages\ListAtkDepartmentStockMovements;
use App\Models\AtkDepartmentStockMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AtkDepartmentStockMovementResource extends Resource
{
    protected static ?string $model = AtkDepartmentStockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsUpDown;

    protected static ?string $navigationLabel = 'Mutasi Departemen';

    protected static ?string $modelLabel = 'Mutasi Stok Departemen';

    protected static ?string $pluralModelLabel = 'Mutasi Stok Departemen';

    protected static ?int $navigationSort = 9;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('department.name')->label('Departemen')->searchable()->sortable(),
                TextColumn::make('item.name')->label('Barang ATK')->searchable(),
                TextColumn::make('movement_type')->label('Jenis')->badge()->formatStateUsing(fn (string $state): string => str($state)->title()->toString()),
                TextColumn::make('qty')->label('Jumlah')->numeric(decimalPlaces: 2),
                TextColumn::make('balance_after')->label('Saldo akhir')->numeric(decimalPlaces: 2),
                TextColumn::make('request.request_number')->label('Permintaan')->searchable()->toggleable(),
                TextColumn::make('performer.name')->label('Oleh')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('movement_type')->label('Jenis')->options(['received' => 'Diterima', 'used' => 'Digunakan']),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['department', 'item', 'request', 'performer']);
        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasPermission('atk.manage') || $user->hasPermission('atk.report')) {
            return $query;
        }

        $user->loadMissing('employee');

        return $query->where('department_id', $user->employee?->department_id ?? 0);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('atk.department-movement') === true;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'ATK';
    }

    public static function getPages(): array
    {
        return ['index' => ListAtkDepartmentStockMovements::route('/')];
    }
}
