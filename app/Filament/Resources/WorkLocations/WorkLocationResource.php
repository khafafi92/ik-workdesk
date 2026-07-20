<?php

namespace App\Filament\Resources\WorkLocations;
use App\Filament\Resources\Concerns\AdminOnlyResource;
use App\Filament\Resources\WorkLocations\Pages\CreateWorkLocation;
use App\Filament\Resources\WorkLocations\Pages\EditWorkLocation;
use App\Filament\Resources\WorkLocations\Pages\ListWorkLocations;
use App\Filament\Resources\WorkLocations\Schemas\WorkLocationForm;
use App\Filament\Resources\WorkLocations\Tables\WorkLocationsTable;
use App\Models\WorkLocation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkLocationResource extends Resource
{
    use AdminOnlyResource;
    protected static ?string $model = WorkLocation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $recordTitleAttribute = 'gps_name';

    public static function form(Schema $schema): Schema
    {
        return WorkLocationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkLocationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getNavigationLabel(): string
    {
        return 'Work Locations';
    }

    public static function getModelLabel(): string
    {
        return 'Work Location';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Work Locations';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Attendance Report';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkLocations::route('/'),
            'create' => CreateWorkLocation::route('/create'),
            'edit' => EditWorkLocation::route('/{record}/edit'),
        ];
    }
}
