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

    protected static ?string $navigationLabel = 'Department Stock Balances';

    protected static ?string $modelLabel = 'Department Stock Balances';

    protected static ?string $pluralModelLabel = 'Department Stock Balances';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('department.name')->label('Department')->searchable()->sortable(),
                TextColumn::make('item.code')->label('Code')->searchable(),
                TextColumn::make('item.name')->label('Office Supply Items')->searchable()->sortable(),
                TextColumn::make('qty_available')->label('Balance')->numeric(decimalPlaces: 0)->sortable(),
                TextColumn::make('item.unit')->label('Unit'),
                TextColumn::make('updated_at')->label('Updated')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('department_id')->label('Department')->relationship('department', 'name')->searchable()->preload(),
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
        return auth()->user()?->hasPermission('atk.department-balance') === true
            || auth()->user()?->hasPermission('atk.request') === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasPermission('atk.department-balance') === true;
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
