<?php

namespace App\Filament\Resources\VehicleBookings;

use App\Filament\Resources\VehicleBookings\Pages\CreateVehicleBooking;
use App\Filament\Resources\VehicleBookings\Pages\EditVehicleBooking;
use App\Filament\Resources\VehicleBookings\Pages\ListVehicleBookings;
use App\Filament\Resources\VehicleBookings\Schemas\VehicleBookingForm;
use App\Filament\Resources\VehicleBookings\Tables\VehicleBookingsTable;
use App\Models\VehicleBooking;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class VehicleBookingResource extends Resource
{
    protected static ?string $model = VehicleBooking::class;

    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-map';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'Bookings';

    protected static ?string $modelLabel = 'Vehicle Booking';

    protected static ?string $pluralModelLabel = 'Vehicle Bookings';

    protected static string|UnitEnum|null $navigationGroup =
        'Vehicle Booking';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return VehicleBookingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VehicleBookingsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['vehicle', 'requester.employee.department']);
        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return static::canManageAll()
            ? $query
            : $query->where('requester_id', $user->id);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return auth()->user()
            ?->can('viewAny', VehicleBooking::class) === true;
    }

    public static function canCreate(): bool
    {
        return auth()->user()
            ?->can('create', VehicleBooking::class) === true;
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

    public static function canManageAll(): bool
    {
        $user = auth()->user();

        return $user?->can(
            'manageAny',
            VehicleBooking::class
        ) === true;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicleBookings::route('/'),
            'create' => CreateVehicleBooking::route('/create'),
            'edit' => EditVehicleBooking::route('/{record}/edit'),
        ];
    }
}
