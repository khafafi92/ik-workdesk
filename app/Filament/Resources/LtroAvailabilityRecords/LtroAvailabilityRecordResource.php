<?php

namespace App\Filament\Resources\LtroAvailabilityRecords;

use App\Filament\Resources\LtroAvailabilityRecords\Pages\ListLtroAvailabilityRecords;
use App\Models\LtroAvailabilityRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LtroAvailabilityRecordResource extends Resource
{
    protected static ?string $model = LtroAvailabilityRecord::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Availability LTRO 1B';

    protected static ?string $modelLabel = 'Availability record';

    protected static ?string $pluralModelLabel = 'Availability LTRO 1B';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'LTRO Management';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('ltro.availability') ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('report_date')->label('Date')->date('d M Y')->sortable(),
            TextColumn::make('day_name')->label('Day'),
            TextColumn::make('rental_code')->label('Rental')->placeholder('-'),
            TextColumn::make('flow_rate')->label('Flow rate')->numeric(decimalPlaces: 2)->placeholder('-'),
            TextColumn::make('availability_percent')->label('Availability')->suffix('%')->numeric(decimalPlaces: 2)->placeholder('-'),
            TextColumn::make('reliability_percent')->label('Reliability')->suffix('%')->numeric(decimalPlaces: 2)->placeholder('-'),
            TextColumn::make('doe_percent')->label('DOE')->suffix('%')->numeric(decimalPlaces: 2)->placeholder('-'),
            TextColumn::make('remark')->label('Remark')->limit(50)->tooltip(fn (LtroAvailabilityRecord $record): ?string => $record->remark),
        ])->filters([
            SelectFilter::make('period_start')->label('Period')->options(fn (): array => LtroAvailabilityRecord::query()->select(['period_start', 'period_end'])->distinct()->orderByDesc('period_start')->get()->mapWithKeys(fn (LtroAvailabilityRecord $record): array => [$record->period_start->toDateString() => $record->period_start->format('d M Y').' - '.$record->period_end->format('d M Y')])->all()),
        ])->defaultSort('report_date', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListLtroAvailabilityRecords::route('/')];
    }
}
