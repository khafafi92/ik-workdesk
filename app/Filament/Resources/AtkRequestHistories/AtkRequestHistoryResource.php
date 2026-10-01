<?php

namespace App\Filament\Resources\AtkRequestHistories;

use App\Filament\Resources\AtkRequestHistories\Pages\ListAtkRequestHistories;
use App\Models\AtkRequestHistory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AtkRequestHistoryResource extends Resource
{
    protected static ?string $model = AtkRequestHistory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Audit Permintaan';

    protected static ?string $modelLabel = 'Audit Permintaan ATK';

    protected static ?string $pluralModelLabel = 'Audit Permintaan ATK';

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('request.request_number')->label('Nomor permintaan')->searchable(),
                TextColumn::make('action')->label('Aktivitas')->badge()->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title()->toString()),
                TextColumn::make('performer.name')->label('Oleh')->searchable(),
                TextColumn::make('note')->label('Catatan')->limit(55)->tooltip(fn (AtkRequestHistory $record): ?string => $record->note),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['request', 'performer']);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('atk.request-history') === true;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'ATK';
    }

    public static function getPages(): array
    {
        return ['index' => ListAtkRequestHistories::route('/')];
    }
}
