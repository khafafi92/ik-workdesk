<?php

namespace App\Filament\Resources\LocationReports;

use App\Filament\Resources\LocationReports\Pages\CreateLocationReport;
use App\Filament\Resources\LocationReports\Pages\EditLocationReport;
use App\Filament\Resources\LocationReports\Pages\ListLocationReports;
use App\Filament\Resources\LocationReports\Schemas\LocationReportForm;
use App\Filament\Resources\LocationReports\Tables\LocationReportsTable;
use App\Models\AttendanceImport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LocationReportResource extends Resource
{
    protected static ?string $model = AttendanceImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $recordTitleAttribute = 'period_name';

    public static function form(Schema $schema): Schema
    {
        return LocationReportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LocationReportsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNotNull('attendance_file_path')
            ->whereNull('work_hour_file_path');
    }

    public static function getNavigationLabel(): string
    {
        return 'Lokasi Absen';
    }

    public static function getModelLabel(): string
    {
        return 'Lokasi Absen';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Lokasi Absen';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Attendance Report';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLocationReports::route('/'),
            'create' => CreateLocationReport::route('/create'),
            'edit' => EditLocationReport::route('/{record}/edit'),
        ];
    }
}
