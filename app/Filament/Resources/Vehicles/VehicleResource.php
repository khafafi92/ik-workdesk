<?php

namespace App\Filament\Resources\Vehicles;

use App\Filament\Resources\Vehicles\Pages\CreateVehicle;
use App\Filament\Resources\Vehicles\Pages\EditVehicle;
use App\Filament\Resources\Vehicles\Pages\ListVehicles;
use App\Filament\Resources\Vehicles\Schemas\VehicleForm;
use App\Filament\Resources\Vehicles\Tables\VehiclesTable;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-truck';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Vehicles';

    protected static ?string $modelLabel = 'Vehicle';

    protected static ?string $pluralModelLabel = 'Vehicles';

    protected static string|UnitEnum|null $navigationGroup =
        'Vehicle Booking';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return VehicleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VehiclesTable::configure($table);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return auth()->user()
            ?->can('viewAny', Vehicle::class) === true;
    }

    public static function canCreate(): bool
    {
        return auth()->user()
            ?->can('create', Vehicle::class) === true;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()
            ?->can('update', $record) === true;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()
            ?->can('delete', $record) === true;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicles::route('/'),
            'create' => CreateVehicle::route('/create'),
            'edit' => EditVehicle::route('/{record}/edit'),
        ];
    }
}
