<?php

namespace App\Filament\Resources\MeetingBookings;

use App\Filament\Resources\MeetingBookings\Pages\CreateMeetingBooking;
use App\Filament\Resources\MeetingBookings\Pages\EditMeetingBooking;
use App\Filament\Resources\MeetingBookings\Pages\ListMeetingBookings;
use App\Filament\Resources\MeetingBookings\Schemas\MeetingBookingForm;
use App\Filament\Resources\MeetingBookings\Tables\MeetingBookingsTable;
use App\Models\MeetingBooking;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class MeetingBookingResource extends Resource
{
    protected static ?string $model = MeetingBooking::class;

    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-calendar-days';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'Bookings';

    protected static ?string $modelLabel = 'Meeting Booking';

    protected static ?string $pluralModelLabel = 'Meeting Bookings';

    protected static string|UnitEnum|null $navigationGroup =
        'Meeting Room';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return MeetingBookingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MeetingBookingsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'room',
            'organizer.employee.department',
            'participants',
        ]);
        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if (static::canManageAll()) {
            return $query;
        }

        return $query->where(
            fn (Builder $bookingQuery) => $bookingQuery
                ->where('organizer_id', $user->id)
                ->orWhereHas(
                    'participants',
                    fn (Builder $participantQuery) => $participantQuery->whereKey($user->id)
                )
        );
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return auth()->user()
            ?->can('viewAny', MeetingBooking::class) === true;
    }

    public static function canCreate(): bool
    {
        return auth()->user()
            ?->can('create', MeetingBooking::class) === true;
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
            MeetingBooking::class
        ) === true;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMeetingBookings::route('/'),
            'create' => CreateMeetingBooking::route('/create'),
            'edit' => EditMeetingBooking::route('/{record}/edit'),
        ];
    }
}
