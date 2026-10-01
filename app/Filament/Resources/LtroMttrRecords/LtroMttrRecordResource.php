<?php

namespace App\Filament\Resources\LtroMttrRecords;

use App\Filament\Resources\LtroMttrRecords\Pages\CreateLtroMttrRecord;
use App\Filament\Resources\LtroMttrRecords\Pages\EditLtroMttrRecord;
use App\Filament\Resources\LtroMttrRecords\Pages\ListLtroMttrRecords;
use App\Models\LtroMttrRecord;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LtroMttrRecordResource extends Resource
{
    protected static ?string $model = LtroMttrRecord::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'MTTR Records';

    protected static ?string $modelLabel = 'MTTR Record';

    protected static ?string $pluralModelLabel = 'MTTR Records';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return 'LTRO Management';
    }

    public static function getNavigationUrl(): string
    {
        return route('ltro.monitoring');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('ltro.mttr-records') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasPermission('ltro.manage') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasPermission('ltro.manage') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasPermission('ltro.manage') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Shutdown event')->schema([
                TextInput::make('sequence_no')->numeric()->minValue(1),
                Select::make('ltro_unit_id')->label('Unit')->relationship('unit', 'name')->searchable()->preload()->required(),
                Select::make('ltro_category_id')->label('Category')->relationship('category', 'name')->searchable()->preload(),
                TextInput::make('rental_period')->maxLength(255),
                DatePicker::make('shutdown_month')->label('Report month')->native(false),
                DateTimePicker::make('shutdown_datetime')->label('Shutdown at')->seconds(false)->required(),
                DateTimePicker::make('running_datetime')->label('Running at')->seconds(false)->after('shutdown_datetime'),
                TextInput::make('downtime_minutes')->label('Downtime (minutes)')->numeric()->minValue(0)->required(),
                TextInput::make('running_hours')->numeric()->step(0.01),
                TextInput::make('pk_100')->label('PK 100')->numeric()->step(0.01),
            ])->columns(2),
            Section::make('Event details')->schema([
                Textarea::make('indication')->rows(3)->columnSpanFull(),
                Textarea::make('immediate_cause')->label('Immediate cause')->rows(3)->columnSpanFull(),
                Textarea::make('activity_troubleshooting')->label('Activity / troubleshooting')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('sequence_no')->label('No.')->sortable(),
            TextColumn::make('unit.name')->label('Unit')->sortable()->searchable(),
            TextColumn::make('category.name')->label('Category')->badge()->placeholder('-'),
            TextColumn::make('shutdown_datetime')->label('Shutdown')->dateTime('d M Y H:i')->sortable(),
            TextColumn::make('running_datetime')->label('Running')->dateTime('d M Y H:i')->placeholder('-'),
            TextColumn::make('downtime_minutes')->label('Downtime')->suffix(' min')->sortable(),
            TextColumn::make('immediate_cause')->label('Immediate cause')->limit(60)->tooltip(fn (LtroMttrRecord $record): ?string => $record->immediate_cause),
        ])->filters([
            SelectFilter::make('ltro_unit_id')->label('Unit')->relationship('unit', 'name'),
            SelectFilter::make('ltro_category_id')->label('Category')->relationship('category', 'name'),
        ])->defaultSort('shutdown_datetime', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLtroMttrRecords::route('/'),
            'create' => CreateLtroMttrRecord::route('/create'),
            'edit' => EditLtroMttrRecord::route('/{record}/edit'),
        ];
    }
}
