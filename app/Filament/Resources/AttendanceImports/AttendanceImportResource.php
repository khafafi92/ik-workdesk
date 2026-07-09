<?php

namespace App\Filament\Resources\AttendanceImports;

use App\Filament\Resources\AttendanceImports\Pages\CreateAttendanceImport;
use App\Filament\Resources\AttendanceImports\Pages\EditAttendanceImport;
use App\Filament\Resources\AttendanceImports\Pages\ListAttendanceImports;
use App\Filament\Resources\AttendanceImports\Pages\ViewAttendanceImportResults;
use App\Filament\Resources\AttendanceImports\Schemas\AttendanceImportForm;
use App\Filament\Resources\AttendanceImports\Tables\AttendanceImportsTable;
use App\Models\AttendanceImport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AttendanceImportResource extends Resource
{
    protected static ?string $model = AttendanceImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    
    protected static ?string $recordTitleAttribute = 'period_name';

    protected static function currentUserCanUploadAttendance(): bool
    {
        $user = auth()->user();

        return $user !== null
            && (
                $user->hasPermission('attendance.upload')
                || $user->hasPermission('attendance.manage')
            );
    }

    public static function form(Schema $schema): Schema
    {
        return AttendanceImportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendanceImportsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }


    public static function getNavigationLabel(): string
    {
        return 'Upload Period';
    }

    public static function getModelLabel(): string
    {
        return 'Attendance Upload';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Attendance Uploads';
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
        return static::currentUserCanUploadAttendance();
    }

    public static function canViewAny(): bool
    {
        return static::currentUserCanUploadAttendance();
    }

    public static function canView(Model $record): bool
    {
        return static::currentUserCanUploadAttendance();
    }

    public static function canCreate(): bool
    {
        return static::currentUserCanUploadAttendance();
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
            'index' => ListAttendanceImports::route('/'),
            'create' => CreateAttendanceImport::route('/create'),
            'edit' => EditAttendanceImport::route('/{record}/edit'),
            'results' => ViewAttendanceImportResults::route('/{record}/results'),
        ];
    }
}
