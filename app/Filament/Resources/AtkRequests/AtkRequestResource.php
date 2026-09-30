<?php

namespace App\Filament\Resources\AtkRequests;

use App\Filament\Resources\AtkRequests\Pages\CreateAtkRequest;
use App\Filament\Resources\AtkRequests\Pages\ListAtkRequests;
use App\Models\AtkItem;
use App\Models\AtkRequest;
use App\Models\AtkRequestItem;
use App\Models\PermitCompany;
use App\Services\AtkDepartmentStockService;
use App\Services\AtkWarehouseStockService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AtkRequestResource extends Resource
{
    protected static ?string $model = AtkRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'request_number';

    protected static ?string $navigationLabel = 'Permintaan ATK';

    protected static ?string $modelLabel = 'Permintaan ATK';

    protected static ?string $pluralModelLabel = 'Permintaan ATK';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi permintaan')
                ->columns(2)
                ->schema([
                    TextInput::make('request_number')
                        ->label('Nomor permintaan')
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('Dibuat saat permintaan dikirim'),
                    TextInput::make('request_date')
                        ->label('Tanggal permintaan')
                        ->default(now()->toDateString())
                        ->disabled()
                        ->dehydrated(false),
                    Textarea::make('purpose')
                        ->label('Keperluan')
                        ->required()
                        ->columnSpanFull()
                        ->maxLength(1000),
                    Select::make('permit_company_id')
                        ->label('Entitas peminta')
                        ->relationship(
                            'company',
                            'name',
                            fn (Builder $query): Builder => $query
                                ->where('is_active', true)
                                ->orderBy('name')
                        )
                        ->getOptionLabelFromRecordUsing(
                            fn (PermitCompany $record): string => "{$record->code} — {$record->name}"
                        )
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpanFull(),
                ]),
            Section::make('Daftar barang')
                ->description('Jumlah yang diminta tidak dapat diubah setelah permintaan dikirim.')
                ->schema([
                    Repeater::make('items')
                        ->relationship()
                        ->addActionLabel('Tambah barang')
                        ->minItems(1)
                        ->defaultItems(1)
                        ->columns(12)
                        ->schema([
                            Select::make('atk_item_id')
                                ->label('Barang ATK')
                                ->relationship(
                                    'item',
                                    'name',
                                    fn (Builder $query): Builder => $query
                                        ->where('is_active', true)
                                        ->orderBy('name')
                                )
                                ->getOptionLabelFromRecordUsing(
                                    fn (AtkItem $record): string => "{$record->code} — {$record->name}"
                                )
                                ->searchable()
                                ->preload()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->live()
                                ->afterStateUpdated(function (mixed $state, Set $set): void {
                                    $set('unit', AtkItem::query()->find($state)?->unit ?? '');
                                })
                                ->required()
                                ->columnSpan(6),
                            TextInput::make('qty_requested')
                                ->label('Jumlah')
                                ->numeric()
                                ->minValue(0.01)
                                ->step(0.01)
                                ->required()
                                ->columnSpan(2),
                            TextInput::make('unit')
                                ->label('Satuan')
                                ->default('pcs')
                                ->required()
                                ->maxLength(30)
                                ->columnSpan(2),
                            TextInput::make('requester_note')
                                ->label('Catatan')
                                ->maxLength(500)
                                ->columnSpan(12),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('request_number')
                    ->label('Nomor')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('request_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('requester.name')
                    ->label('Peminta')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('department.name')
                    ->label('Departemen')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('company.code')
                    ->label('Entitas')
                    ->badge()
                    ->color('info')
                    ->toggleable(),
                TextColumn::make('items_count')
                    ->label('Item')
                    ->counts('items')
                    ->alignCenter(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title()->toString())
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'partially_fulfilled', 'processing' => 'warning',
                        'cancelled' => 'danger',
                        default => 'info',
                    }),
                TextColumn::make('purpose')
                    ->label('Keperluan')
                    ->limit(45)
                    ->tooltip(fn (AtkRequest $record): string => $record->purpose),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(static::statusOptions()),
                SelectFilter::make('department_id')
                    ->label('Departemen')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('permit_company_id')
                    ->label('Entitas')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->visible(fn (): bool => static::canCreate()),
            ])
            ->recordActions([
                Action::make('waiting_procurement')
                    ->label('Tunggu pengadaan')
                    ->icon(Heroicon::OutlinedClock)
                    ->color('warning')
                    ->visible(fn (AtkRequest $record): bool => static::canManage() && $record->status !== 'completed')
                    ->form(static::itemActionForm('Item yang menunggu pengadaan'))
                    ->action(function (AtkRequest $record, array $data): void {
                        app(AtkWarehouseStockService::class)->markWaitingProcurement(
                            static::requestItem($record, $data['request_item_id']),
                            auth()->user(),
                            $data['ga_note'] ?? null,
                        );
                        static::success('Item ditandai menunggu pengadaan.');
                    }),
                Action::make('mark_ready')
                    ->label('Siap diserahkan')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('info')
                    ->visible(fn (AtkRequest $record): bool => static::canManage() && $record->status !== 'completed')
                    ->form(static::itemActionForm('Item yang sudah tersedia'))
                    ->action(function (AtkRequest $record, array $data): void {
                        app(AtkWarehouseStockService::class)->markReady(
                            static::requestItem($record, $data['request_item_id']),
                            auth()->user(),
                            $data['ga_note'] ?? null,
                        );
                        static::success('Item ditandai siap diserahkan.');
                    }),
                Action::make('issue')
                    ->label('Serahkan barang')
                    ->icon(Heroicon::OutlinedArrowRightCircle)
                    ->color('primary')
                    ->visible(fn (AtkRequest $record): bool => static::canManage() && $record->status !== 'completed')
                    ->form([
                        ...static::itemActionForm('Item yang diserahkan'),
                        TextInput::make('quantity')
                            ->label('Jumlah diserahkan')
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->required(),
                    ])
                    ->action(function (AtkRequest $record, array $data): void {
                        app(AtkWarehouseStockService::class)->issue(
                            static::requestItem($record, $data['request_item_id']),
                            (float) $data['quantity'],
                            auth()->user(),
                        );
                        static::success('Penyerahan barang tercatat.');
                    }),
                Action::make('confirm_received')
                    ->label('Konfirmasi terima')
                    ->icon(Heroicon::OutlinedHandThumbUp)
                    ->color('success')
                    ->visible(fn (AtkRequest $record): bool => static::canConfirmReceipt($record))
                    ->form([
                        Select::make('request_item_id')
                            ->label('Item diterima')
                            ->options(fn (AtkRequest $record): array => static::receivableItems($record))
                            ->required(),
                    ])
                    ->action(function (AtkRequest $record, array $data): void {
                        app(AtkDepartmentStockService::class)->receive(
                            static::requestItem($record, $data['request_item_id']),
                            auth()->user(),
                        );
                        static::success('Penerimaan dan saldo departemen telah diperbarui.');
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['requester', 'department', 'company']);
        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return static::canManage() ? $query : $query->where('requester_id', $user->id);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('atk.request') === true
            || static::canManage();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasPermission('atk.request') === true;
    }

    public static function canManage(): bool
    {
        return auth()->user()?->hasPermission('atk.manage') === true;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'ATK';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAtkRequests::route('/'),
            'create' => CreateAtkRequest::route('/create'),
        ];
    }

    private static function itemActionForm(string $label): array
    {
        return [
            Select::make('request_item_id')
                ->label($label)
                ->options(fn (AtkRequest $record): array => static::outstandingItems($record))
                ->required(),
            Textarea::make('ga_note')->label('Catatan GA')->maxLength(500),
        ];
    }

    private static function outstandingItems(AtkRequest $record): array
    {
        return $record->items()->with('item')->get()
            ->filter(fn (AtkRequestItem $item): bool => $item->outstandingRequested() > 0)
            ->mapWithKeys(fn (AtkRequestItem $item): array => [
                $item->id => "{$item->item->name} (sisa: ".number_format($item->outstandingRequested(), 2, ',', '.').')',
            ])->all();
    }

    private static function receivableItems(AtkRequest $record): array
    {
        return $record->items()->with('item')->get()
            ->filter(fn (AtkRequestItem $item): bool => $item->awaitingReceipt() > 0)
            ->mapWithKeys(fn (AtkRequestItem $item): array => [
                $item->id => "{$item->item->name} (menunggu: ".number_format($item->awaitingReceipt(), 2, ',', '.').')',
            ])->all();
    }

    private static function requestItem(AtkRequest $request, int|string $itemId): AtkRequestItem
    {
        return $request->items()->findOrFail($itemId);
    }

    private static function canConfirmReceipt(AtkRequest $record): bool
    {
        return (int) $record->requester_id === (int) auth()->id()
            && $record->items()->get()->contains(
                fn (AtkRequestItem $item): bool => $item->awaitingReceipt() > 0
            );
    }

    private static function statusOptions(): array
    {
        return [
            'submitted' => 'Submitted',
            'processing' => 'Processing',
            'partially_fulfilled' => 'Partially fulfilled',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];
    }

    private static function success(string $title): void
    {
        Notification::make()->title($title)->success()->send();
    }
}
