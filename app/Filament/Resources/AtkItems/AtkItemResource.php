<?php

namespace App\Filament\Resources\AtkItems;

use App\Exports\AtkItemImportTemplateExport;
use App\Filament\Resources\AtkItems\Pages\CreateAtkItem;
use App\Filament\Resources\AtkItems\Pages\EditAtkItem;
use App\Filament\Resources\AtkItems\Pages\ListAtkItems;
use App\Models\AtkItem;
use App\Services\AtkItemImportService;
use App\Services\AtkWarehouseStockService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class AtkItemResource extends Resource
{
    protected static ?string $model = AtkItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Master Barang ATK';

    protected static ?string $modelLabel = 'Barang ATK';

    protected static ?string $pluralModelLabel = 'Master Barang ATK';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data barang')
                ->columns(2)
                ->schema([
                    TextInput::make('code')->label('Kode barang')->required()->maxLength(50)->unique(ignoreRecord: true),
                    TextInput::make('name')->label('Nama barang')->required()->maxLength(255),
                    Select::make('atk_category_id')
                        ->label('Kategori')
                        ->relationship('categoryMaster', 'name', fn (Builder $query): Builder => $query->where('is_active', true))
                        ->searchable()
                        ->preload(),
                    Select::make('atk_unit_id')
                        ->label('Satuan')
                        ->relationship('unitMaster', 'name', fn (Builder $query): Builder => $query->where('is_active', true))
                        ->searchable()
                        ->preload()
                        ->required(),
                    TextInput::make('minimum_stock')->label('Stok minimum')->numeric()->minValue(0)->step(0.01),
                    Toggle::make('is_active')->label('Aktif')->default(true),
                    TextInput::make('current_stock')
                        ->label('Stok awal Gudang Utama')
                        ->helperText('Stok berjalan Gudang Utama hanya diubah melalui transaksi masuk atau penyesuaian.')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->default(0)
                        ->disabledOn('edit'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama barang')->searchable()->sortable(),
                TextColumn::make('category')->label('Kategori')->toggleable(),
                TextColumn::make('current_stock')
                    ->label('Stok Gudang Utama')
                    ->numeric(decimalPlaces: 0)
                    ->sortable()
                    ->color(fn (AtkItem $record): string => $record->minimum_stock !== null && $record->current_stock <= $record->minimum_stock ? 'danger' : 'success'),
                TextColumn::make('minimum_stock')->label('Min. stok')->numeric(decimalPlaces: 2)->toggleable(),
                TextColumn::make('unit')->label('Satuan'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Aktif'),
            ])
            ->defaultSort('name')
            ->headerActions([
                Action::make('download_import_template')
                    ->label('Template Excel')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->action(fn () => Excel::download(
                        new AtkItemImportTemplateExport,
                        'template-import-master-atk.xlsx',
                    )),
                Action::make('import_items')
                    ->label('Import Master/Stok')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->color('primary')
                    ->modalHeading('Import master dan stok ATK')
                    ->modalDescription('Gunakan template Excel. Untuk item lama, kolom current_stock menjadi saldo target Gudang Utama dan selisihnya dicatat sebagai mutasi.')
                    ->form([
                        FileUpload::make('file')
                            ->label('File Excel')
                            ->disk('local')
                            ->directory('atk-imports')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                                'text/csv',
                            ])
                            ->preserveFilenames()
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $result = app(AtkItemImportService::class)->import(
                            $data['file'],
                            auth()->user(),
                        );

                        Notification::make()
                            ->title('Import ATK selesai')
                            ->body("Baru: {$result['created']}; diperbarui: {$result['updated']}; stok disesuaikan: {$result['stockAdjusted']}.")
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('incoming')
                    ->label('Stok masuk')
                    ->icon(Heroicon::OutlinedArrowDownCircle)
                    ->color('success')
                    ->form(static::stockForm('Jumlah masuk'))
                    ->action(function (AtkItem $record, array $data): void {
                        app(AtkWarehouseStockService::class)->incoming(
                            $record,
                            (float) $data['quantity'],
                            auth()->user(),
                            $data['note'] ?? null,
                        );
                        static::success('Stok masuk tercatat.');
                    }),
                Action::make('adjust')
                    ->label('Penyesuaian')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->color('warning')
                    ->form(static::stockForm('Selisih stok (+/-)', true))
                    ->requiresConfirmation()
                    ->modalDescription('Masukkan nilai positif untuk menambah dan nilai negatif untuk mengurangi stok.')
                    ->action(function (AtkItem $record, array $data): void {
                        app(AtkWarehouseStockService::class)->adjust(
                            $record,
                            (float) $data['quantity'],
                            auth()->user(),
                            $data['note'] ?? null,
                        );
                        static::success('Penyesuaian stok tercatat.');
                    }),
                EditAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('atk.manage') === true;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationGroup(): ?string
    {
        return 'ATK';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAtkItems::route('/'),
            'create' => CreateAtkItem::route('/create'),
            'edit' => EditAtkItem::route('/{record}/edit'),
        ];
    }

    private static function stockForm(string $quantityLabel, bool $allowNegative = false): array
    {
        return [
            TextInput::make('quantity')
                ->label($quantityLabel)
                ->numeric()
                ->step(0.01)
                ->minValue($allowNegative ? null : 0.01)
                ->required(),
            TextInput::make('note')->label('Catatan')->required()->maxLength(500),
        ];
    }

    private static function success(string $title): void
    {
        Notification::make()->title($title)->success()->send();
    }
}
