<?php

namespace App\Filament\Resources\AtkUsageTransactions;

use App\Filament\Resources\AtkUsageTransactions\Pages\CreateAtkUsageTransaction;
use App\Filament\Resources\AtkUsageTransactions\Pages\ListAtkUsageTransactions;
use App\Models\AtkDepartmentBalance;
use App\Models\AtkUsageTransaction;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AtkUsageTransactionResource extends Resource
{
    protected static ?string $model = AtkUsageTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Pemakaian ATK';

    protected static ?string $modelLabel = 'Pemakaian ATK';

    protected static ?string $pluralModelLabel = 'Riwayat Pemakaian ATK';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        $departmentId = auth()->user()?->employee?->department_id;

        return $schema->components([
            Section::make('Catat pemakaian')
                ->columns(2)
                ->schema([
                    Select::make('atk_item_id')
                        ->label('Barang ATK')
                        ->options(fn (): array => AtkDepartmentBalance::query()
                            ->where('department_id', $departmentId ?? 0)
                            ->where('qty_available', '>', 0)
                            ->with('item')
                            ->get()
                            ->mapWithKeys(fn (AtkDepartmentBalance $balance): array => [
                                $balance->atk_item_id => "{$balance->item->name} (tersedia: ".number_format((float) $balance->qty_available, 2, ',', '.').')',
                            ])->all())
                        ->searchable()
                        ->required(),
                    DatePicker::make('usage_date')->label('Tanggal pemakaian')->default(today())->required(),
                    TextInput::make('qty_used')->label('Jumlah dipakai')->numeric()->minValue(0.01)->step(0.01)->required(),
                    Textarea::make('purpose')->label('Keperluan')->required()->maxLength(1000)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('usage_date')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('department.name')->label('Departemen')->searchable()->toggleable(),
                TextColumn::make('item.name')->label('Barang ATK')->searchable()->sortable(),
                TextColumn::make('qty_used')->label('Jumlah')->numeric(decimalPlaces: 2),
                TextColumn::make('purpose')->label('Keperluan')->limit(55)->tooltip(fn (AtkUsageTransaction $record): string => $record->purpose),
                TextColumn::make('user.name')->label('Dicatat oleh')->toggleable(),
            ])
            ->defaultSort('usage_date', 'desc')
            ->headerActions([CreateAction::make()->visible(fn (): bool => static::canCreate())]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['department', 'item', 'user']);
        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasPermission('atk.manage') || $user->hasPermission('atk.report')) {
            return $query;
        }

        $user->loadMissing('employee');

        return $query->where('department_id', $user->employee?->department_id ?? 0);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('atk.request') === true
            || auth()->user()?->hasPermission('atk.manage') === true
            || auth()->user()?->hasPermission('atk.report') === true;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasPermission('atk.request') === true;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'ATK';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAtkUsageTransactions::route('/'),
            'create' => CreateAtkUsageTransaction::route('/create'),
        ];
    }
}
