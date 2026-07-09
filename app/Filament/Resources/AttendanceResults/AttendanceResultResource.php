<?php

namespace App\Filament\Resources\AttendanceResults;

use App\Filament\Resources\AttendanceResults\Pages\CreateAttendanceResult;
use App\Filament\Resources\AttendanceResults\Pages\EditAttendanceResult;
use App\Filament\Resources\AttendanceResults\Pages\ListAttendanceResults;
use App\Filament\Resources\AttendanceResults\Schemas\AttendanceResultForm;
use App\Filament\Resources\AttendanceResults\Tables\AttendanceResultsTable;
use App\Models\AttendanceResult;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AttendanceResultResource extends Resource
{
    protected static ?string $model = AttendanceResult::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'employee_name';

    protected static bool $shouldRegisterNavigation = false;

    protected static function currentUserCanViewAttendance(): bool
    {
        return auth()->user()
            ?->hasPermission('attendance.view') === true;
    }

    public static function form(Schema $schema): Schema
    {
        return AttendanceResultForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendanceResultsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function canCreate(): bool
{
    return auth()->user()
        ?->hasPermission('attendance.manage') === true;
}

public static function canDeleteAny(): bool
{
    return auth()->user()
        ?->hasPermission('attendance.manage') === true;
}

    public static function getNavigationLabel(): string
{
    return 'Report Results';
}

public static function getModelLabel(): string
{
    return 'Attendance Result';
}

public static function getPluralModelLabel(): string
{
    return 'Attendance Results';
}

public static function getNavigationGroup(): ?string
{
    return 'Attendance Report';
}

public static function getNavigationSort(): ?int
{
    return 2;
}

    public static function shouldRegisterNavigation(): bool
    {
        return static::currentUserCanViewAttendance();
    }

    public static function canViewAny(): bool
    {
        return static::currentUserCanViewAttendance();
    }

    public static function canView(Model $record): bool
    {
        return static::currentUserCanViewAttendance();
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()
            ?->hasPermission('attendance.manage') === true;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()
            ?->hasPermission('attendance.manage') === true;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendanceResults::route('/'),
            'create' => CreateAttendanceResult::route('/create'),
            'edit' => EditAttendanceResult::route('/{record}/edit'),
        ];
    }
}
