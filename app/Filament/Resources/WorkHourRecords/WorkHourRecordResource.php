<?php

namespace App\Filament\Resources\WorkHourRecords;

use App\Filament\Resources\WorkHourRecords\Pages\ListWorkHourRecords;
use App\Filament\Resources\WorkHourRecords\Tables\WorkHourRecordsTable;
use App\Models\WorkHourRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkHourRecordResource extends Resource
{
    protected static ?string $model = WorkHourRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $recordTitleAttribute = 'employee_name';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return WorkHourRecordsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNull('work_date');
    }

    public static function getNavigationLabel(): string
    {
        return 'Work Hour Summaries';
    }

    public static function getModelLabel(): string
    {
        return 'Work Hour Summary';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Work Hour Summaries';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Attendance Report';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkHourRecords::route('/'),
        ];
    }
}
