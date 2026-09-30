<?php

namespace App\Filament\Resources\AtkDepartmentBalances;

use App\Filament\Resources\AtkDepartmentBalances\Pages\ListAtkDepartmentBalances;
use App\Models\AtkDepartmentBalance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AtkDepartmentBalanceResource extends Resource
{
    protected static ?string $model = AtkDepartmentBalance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Stok Departemen';

    protected static ?string $modelLabel = 'Stok Departemen';

    protected static ?string $pluralModelLabel = 'Stok Departemen';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('department.name')->label('Departemen')->searchable()->sortable(),
                TextColumn::make('item.code')->label('Kode')->searchable(),
                TextColumn::make('item.name')->label('Barang ATK')->searchable()->sortable(),
                TextColumn::make('qty_available')->label('Saldo')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('item.unit')->label('Satuan'),
                TextColumn::make('updated_at')->label('Diperbarui')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('department_id')->label('Departemen')->relationship('department', 'name')->searchable()->preload(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['department', 'item']);
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
        return auth()->user()?->hasPermission('atk.request') === true
            || auth()->user()?->hasPermission('atk.manage') === true
            || auth()->user()?->hasPermission('atk.report') === true;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'ATK';
    }

    public static function getPages(): array
    {
        return ['index' => ListAtkDepartmentBalances::route('/')];
    }
}
