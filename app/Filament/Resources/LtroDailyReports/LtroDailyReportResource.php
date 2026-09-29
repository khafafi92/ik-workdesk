<?php

namespace App\Filament\Resources\LtroDailyReports;

use App\Filament\Resources\LtroDailyReports\Pages\ListLtroDailyReports;
use App\Models\LtroDailyReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LtroDailyReportResource extends Resource
{
    protected static ?string $model = LtroDailyReport::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Daily Reports';

    protected static ?string $modelLabel = 'Daily Report';

    protected static ?string $pluralModelLabel = 'Daily Reports';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'LTRO Management';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('ltro.view') ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('report_date')->label('Report date')->date('d M Y')->sortable(),
            TextColumn::make('unit_code')->label('Unit')->badge()->sortable(),
            TextColumn::make('operator_day')->label('Day operator')->placeholder('-'),
            TextColumn::make('operator_night')->label('Night operator')->placeholder('-'),
            TextColumn::make('combined_average')->label('Combined average')->numeric(decimalPlaces: 2)->placeholder('-'),
            TextColumn::make('running_hours')->label('Running')->suffix(' h')->placeholder('-'),
            TextColumn::make('down_reactive_hours')->label('Down reactive')->suffix(' h')->placeholder('-'),
            TextColumn::make('updated_at')->label('Last update')->since()->sortable(),
        ])->filters([
            SelectFilter::make('unit_code')->label('Unit')->options(fn (): array => LtroDailyReport::query()->distinct()->orderBy('unit_code')->pluck('unit_code', 'unit_code')->all()),
        ])->defaultSort('report_date', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListLtroDailyReports::route('/')];
    }
}
