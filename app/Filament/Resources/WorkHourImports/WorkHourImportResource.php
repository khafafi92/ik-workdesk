<?php

namespace App\Filament\Resources\WorkHourImports;

use App\Filament\Resources\WorkHourImports\Pages\CreateWorkHourImport;
use App\Filament\Resources\WorkHourImports\Pages\EditWorkHourImport;
use App\Filament\Resources\WorkHourImports\Pages\ListWorkHourImports;
use App\Filament\Resources\WorkHourImports\Schemas\WorkHourImportForm;
use App\Filament\Resources\WorkHourImports\Tables\WorkHourImportsTable;
use App\Models\AttendanceImport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkHourImportResource extends Resource
{
    protected static ?string $model = AttendanceImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $recordTitleAttribute = 'period_name';

    public static function form(Schema $schema): Schema
    {
        return WorkHourImportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkHourImportsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNull('attendance_file_path')
            ->whereNotNull('work_hour_file_path');
    }

    public static function getNavigationLabel(): string
    {
        return 'Total Jam Kerja';
    }

    public static function getModelLabel(): string
    {
        return 'Total Jam Kerja';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Total Jam Kerja';
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
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkHourImports::route('/'),
            'create' => CreateWorkHourImport::route('/create'),
            'edit' => EditWorkHourImport::route('/{record}/edit'),
        ];
    }
}
