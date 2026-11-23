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

    protected static ?string $navigationLabel = 'Department Stock Movements';

    protected static ?string $modelLabel = 'Department Stock Mutation';

    protected static ?string $pluralModelLabel = 'Department Stock Mutation';

    protected static ?int $navigationSort = 9;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Time')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('department.name')->label('Department')->searchable()->sortable(),
                TextColumn::make('item.name')->label('Office Supply Items')->searchable(),
                TextColumn::make('movement_type')->label('Type')->badge()->formatStateUsing(fn (string $state): string => str($state)->title()->toString()),
                TextColumn::make('qty')->label('Quantity')->numeric(decimalPlaces: 2),
                TextColumn::make('balance_after')->label('End balance')->numeric(decimalPlaces: 2),
                TextColumn::make('request.request_number')->label('Request')->searchable()->toggleable(),
                TextColumn::make('performer.name')->label('By')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('movement_type')->label('Type')->options(['received' => 'Received', 'used' => 'In Use']),
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
