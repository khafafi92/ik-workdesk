<?php

namespace App\Filament\Resources\MeetingRooms;

use App\Filament\Resources\MeetingRooms\Pages\CreateMeetingRoom;
use App\Filament\Resources\MeetingRooms\Pages\EditMeetingRoom;
use App\Filament\Resources\MeetingRooms\Pages\ListMeetingRooms;
use App\Filament\Resources\MeetingRooms\Schemas\MeetingRoomForm;
use App\Filament\Resources\MeetingRooms\Tables\MeetingRoomsTable;
use App\Models\MeetingRoom;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class MeetingRoomResource extends Resource
{
    protected static ?string $model = MeetingRoom::class;

    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-building-office-2';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Meeting Rooms';

    protected static ?string $modelLabel = 'Meeting Room';

    protected static ?string $pluralModelLabel = 'Meeting Rooms';

    protected static string|UnitEnum|null $navigationGroup =
        'Meeting Room';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return MeetingRoomForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MeetingRoomsTable::configure($table);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return auth()->user()
            ?->can('viewAny', MeetingRoom::class) === true;
    }

    public static function canCreate(): bool
    {
        return auth()->user()
            ?->can('create', MeetingRoom::class) === true;
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
            'index' => ListMeetingRooms::route('/'),
            'create' => CreateMeetingRoom::route('/create'),
            'edit' => EditMeetingRoom::route('/{record}/edit'),
        ];
    }
}
