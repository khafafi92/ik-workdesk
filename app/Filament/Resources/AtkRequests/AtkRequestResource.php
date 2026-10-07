<?php

namespace App\Filament\Resources\AtkRequests;

use App\Filament\Resources\AtkRequests\Pages\CreateAtkRequest;
use App\Filament\Resources\AtkRequests\Pages\ListAtkRequests;
use App\Models\AtkItem;
use App\Models\AtkRequest;
use App\Models\AtkRequestItem;
use App\Models\Department;
use App\Models\PermitCompany;
use App\Services\AtkDepartmentStockService;
use App\Services\AtkWarehouseStockService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Placeholder;
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

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi permintaan')
                ->description('Isi keperluan dan entitas yang akan memakai barang. Setelah dikirim, permintaan langsung masuk ke daftar tindak lanjut GA.')
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
                    Select::make('requester_department_id')
                        ->label('Departemen peminta')
                        ->options(fn (): array => Department::query()
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->visible(fn (): bool => static::canChooseRequesterDepartment())
                        ->helperText('Akun Anda belum terhubung ke employee. Pilih department asal permintaan ini.'),
                    Placeholder::make('employee_department_notice')
                        ->label('Departemen peminta')
                        ->content('Akun belum terhubung ke employee dan tidak dapat memilih department. Hubungkan akun melalui User Management sebelum membuat permintaan ATK.')
                        ->visible(fn (): bool => static::hasMissingEmployeeDepartment()),
                    Textarea::make('purpose')
                        ->label('Keperluan')
                        ->helperText('Jelaskan barang ini akan digunakan untuk apa.')
                        ->required()
                        ->columnSpanFull()
                        ->maxLength(1000),
                    Select::make('permit_company_id')
                        ->label('Entitas peminta')
                        ->helperText('Pilih entitas yang akan menggunakan barang.')
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
                ->description('Pilih barang dan jumlah yang dibutuhkan. Setelah dikirim, penyerahan dapat dilakukan bertahap dan progresnya terlihat di daftar permintaan.')
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
                                ->label('Jumlah yang diminta')
                                ->numeric()
                                ->minValue(0.01)
                                ->step(0.01)
                                ->required()
                                ->columnSpan(3),
                            TextInput::make('unit')
                                ->label('Satuan barang')
                                ->default('pcs')
                                ->required()
                                ->maxLength(30)
                                ->columnSpan(3),
                            TextInput::make('requester_note')
                                ->label('Catatan untuk GA')
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
                TextColumn::make('requested_items')
                    ->label('Barang diminta')
                    ->state(fn (AtkRequest $record): array => static::requestedItemsList($record))
                    ->listWithLineBreaks()
                    ->wrap()
                    ->tooltip(fn (AtkRequest $record): string => static::requestedItemsSummary($record)),
                TextColumn::make('item_progress')
                    ->label('Progres barang')
                    ->state(fn (AtkRequest $record): array => static::itemProgressList($record))
                    ->listWithLineBreaks()
                    ->wrap()
                    ->tooltip('Diminta adalah total kebutuhan. Diserahkan adalah jumlah dari Gudang Utama. Diterima adalah jumlah yang sudah dikonfirmasi peminta.'),
                TextColumn::make('next_step')
                    ->label('Langkah berikutnya')
                    ->state(fn (AtkRequest $record): string => static::nextStep($record))
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => static::statusLabel($state))
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
                Action::make('issue')
                    ->label('Serahkan dari Gudang Utama')
                    ->icon(Heroicon::OutlinedArrowRightCircle)
                    ->color('primary')
                    ->visible(fn (AtkRequest $record): bool => static::canManage() && $record->status !== 'completed')
                    ->form([
                        ...static::itemActionForm('Barang yang diserahkan'),
                        TextInput::make('quantity')
                            ->label('Jumlah diserahkan')
                            ->helperText('Masukkan jumlah yang benar-benar diserahkan pada tahap ini.')
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
                    ->label('Konfirmasi barang diterima')
                    ->icon(Heroicon::OutlinedHandThumbUp)
                    ->color('success')
                    ->visible(fn (AtkRequest $record): bool => static::canConfirmReceipt($record))
                    ->form([
                        Select::make('request_item_id')
                            ->label('Barang yang diterima')
                            ->helperText('Pilih barang yang sudah Anda terima. Sistem mencatat seluruh jumlah yang sudah diserahkan dan belum dikonfirmasi.')
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
        $query = parent::getEloquentQuery()->with(['requester', 'department', 'company', 'items.item']);
        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return static::canManage() ? $query : $query->where('requester_id', $user->id);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('atk.requests') === true
            || auth()->user()?->hasPermission('atk.request') === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasPermission('atk.requests') === true;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny()
            && auth()->user()?->hasPermission('atk.request') === true;
    }

    public static function canManage(): bool
    {
        return auth()->user()?->hasPermission('atk.manage') === true;
    }

    public static function canChooseRequesterDepartment(): bool
    {
        $user = auth()->user();
        $user?->loadMissing('employee');

        return $user?->employee?->department_id === null
            && ($user?->is_admin === true || $user?->hasRole('system-admin') === true);
    }

    public static function hasMissingEmployeeDepartment(): bool
    {
        $user = auth()->user();
        $user?->loadMissing('employee');

        return $user?->employee?->department_id === null
            && ! static::canChooseRequesterDepartment();
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
                $item->id => "{$item->item->name} (".number_format($item->outstandingRequested(), 0, ',', '.').')',
            ])->all();
    }

    private static function receivableItems(AtkRequest $record): array
    {
        return $record->items()->with('item')->get()
            ->filter(fn (AtkRequestItem $item): bool => $item->awaitingReceipt() > 0)
            ->mapWithKeys(fn (AtkRequestItem $item): array => [
                $item->id => "{$item->item->name} (menunggu: ".number_format($item->awaitingReceipt(), 0, ',', '.').')',
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
            'submitted' => 'Baru',
            'processing' => 'Diproses',
            'partially_fulfilled' => 'Sebagian dipenuhi',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
        ];
    }

    private static function requestedItemsList(AtkRequest $request): array
    {
        return $request->items
            ->map(fn (AtkRequestItem $item): string => trim(implode(' ', [
                $item->item?->name,
                number_format((float) $item->qty_requested, 0, ',', '.'),
                $item->unit,
            ])))
            ->values()
            ->all();
    }

    private static function requestedItemsSummary(AtkRequest $request): string
    {
        return implode('; ', static::requestedItemsList($request));
    }

    private static function itemProgressList(AtkRequest $request): array
    {
        return $request->items
            ->map(function (AtkRequestItem $item): string {
                $requested = (float) $item->qty_requested;
                $issued = (float) $item->qty_issued;
                $received = (float) $item->qty_received;
                $format = fn (float $quantity): string => number_format($quantity, 0, ',', '.');

                return sprintf(
                    '%s: diminta %s, diserahkan %s, belum diserahkan %s, diterima %s',
                    $item->item?->name ?? 'Barang',
                    $format($requested),
                    $format($issued),
                    $format($item->outstandingRequested()),
                    $format($received),
                );
            })
            ->values()
            ->all();
    }

    private static function nextStep(AtkRequest $record): string
    {
        if ($record->status === 'completed') {
            return 'Permintaan selesai.';
        }

        $awaitingReceipt = $record->items->sum(fn (AtkRequestItem $item): float => $item->awaitingReceipt());
        $outstanding = $record->items->sum(fn (AtkRequestItem $item): float => $item->outstandingRequested());

        if (static::canConfirmReceipt($record) && $awaitingReceipt > 0) {
            return 'Konfirmasi penerimaan barang yang sudah diserahkan.';
        }

        if (static::canManage() && $outstanding > 0) {
            return 'Serahkan sisa barang dari Gudang Utama jika stok tersedia.';
        }

        if ($awaitingReceipt > 0) {
            return 'Menunggu peminta mengonfirmasi penerimaan.';
        }

        return 'Menunggu GA memproses atau menyerahkan barang.';
    }

    private static function statusLabel(string $status): string
    {
        return match ($status) {
            'submitted' => 'Baru',
            'processing' => 'Diproses',
            'partially_fulfilled' => 'Sebagian dipenuhi',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    private static function success(string $title): void
    {
        Notification::make()->title($title)->success()->send();
    }
}
