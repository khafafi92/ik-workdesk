<?php

namespace App\Filament\Resources\AtkRequests;

use App\Filament\LocalizedResource as Resource;
use App\Filament\Pages\PurchaseRequests;
use App\Filament\Resources\AtkRequests\Pages\CreateAtkRequest;
use App\Filament\Resources\AtkRequests\Pages\ListAtkRequests;
use App\Models\AtkItem;
use App\Models\AtkRequest;
use App\Models\AtkRequestItem;
use App\Models\Department;
use App\Models\PermitCompany;
use App\Models\Scm\ProjectNumbering;
use App\Services\AtkDepartmentStockService;
use App\Services\AtkProcurementService;
use App\Services\AtkWarehouseStockService;
use App\Services\Inventory\InventoryStock;
use App\Services\Scm\Access;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AtkRequestResource extends Resource
{
    protected static ?string $model = AtkRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'request_number';

    protected static ?string $navigationLabel = 'Office Supply Requests';

    protected static ?string $modelLabel = 'Office Supply Request';

    protected static ?string $pluralModelLabel = 'Office Supply Requests';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Request information'))
                ->extraAttributes(['class' => 'atk-request-context'])
                ->description(__('The contents and entity that will be used for goods. The request goes directly to the GA follow-up list.'))
                ->columnSpanFull()
                ->columns(12)
                ->schema([
                    TextInput::make('request_number')
                        ->label(__('Request number'))
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder(__('Created when request is sent'))
                        ->columnSpan(2),
                    TextInput::make('request_date')
                        ->label(__('Request date'))
                        ->default(now()->toDateString())
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpan(2),
                    TextInput::make('purpose')
                        ->label(__('Requirement'))
                        ->helperText(__('Explain to me what it\'s going to be used for.'))
                        ->required()
                        ->maxLength(1000)
                        ->columnSpan(5),
                    Select::make('permit_company_id')
                        ->label(__('Requesting entity'))
                        ->helperText(__('Select the entity that will use goods.'))
                        ->relationship(
                            'company',
                            'name',
                            fn (Builder $query): Builder => $query
                                ->where('is_active', true)
                                ->orderBy('name')
                        )
                        ->getOptionLabelFromRecordUsing(
                            fn (PermitCompany $record): string => "{$record->code} - {$record->name}"
                        )
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpan(3),
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
                        ->helperText(__('Your account has not been connected to the emeloyee. Select the department as long as this request is available.'))
                        ->columnSpanFull(),
                    Select::make('need_type')
                        ->label(__('The kind of needs'))
                        ->options(['HO' => 'Head Office', 'PROJECT' => __('Project')])
                        ->default('HO')
                        ->live()
                        ->required()
                        ->columnSpan(3),
                    Select::make('project_numbering_id')
                        ->label('Master kode Project')
                        ->options(fn (): array => ProjectNumbering::where('is_active', true)->orderBy('name')->get()
                            ->mapWithKeys(fn ($project): array => [$project->id => $project->code.' - '.$project->name])->all())
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get): bool => $get('need_type') === 'PROJECT')
                        ->required(fn (Get $get): bool => $get('need_type') === 'PROJECT')
                        ->columnSpan(9),
                    Placeholder::make('employee_department_notice')
                        ->label('Departemen peminta')
                        ->content(__('Account is not connected to emploee and cannot select department. Connect accounts through User Management before making office supplies requests.'))
                        ->visible(fn (): bool => static::hasMissingEmployeeDepartment())
                        ->columnSpanFull(),
                ]),
            Section::make(__('Requested items'))
                ->extraAttributes(['class' => 'atk-request-items'])
                ->description(__('Choose the items and quantities you need. GA can issue items in batches. Track progress in the request list.'))
                ->columnSpanFull()
                ->schema([
                    Repeater::make('items')
                        ->relationship()
                        ->addActionLabel(__('Add item'))
                        ->minItems(1)
                        ->defaultItems(1)
                        ->compact()
                        ->table([
                            TableColumn::make('Office Supply Item')->markAsRequired()->width('40%'),
                            TableColumn::make('Quantity')->markAsRequired()->width('15%'),
                            TableColumn::make('Unit')->markAsRequired()->width('15%'),
                            TableColumn::make('Notes for GA')->width('30%'),
                        ])
                        ->schema([
                            Select::make('atk_item_id')
                                ->label(__('Office Supply Items'))
                                ->relationship(
                                    'item',
                                    'name',
                                    fn (Builder $query): Builder => $query
                                        ->where('is_active', true)
                                        ->orderBy('name')
                                        ->orderBy('size')
                                        ->orderBy('code')
                                )
                                ->getOptionLabelFromRecordUsing(
                                    fn (AtkItem $record): string => implode(' - ', array_filter([
                                        $record->code,
                                        $record->name,
                                        filled($record->size) ? 'Size '.$record->size : null,
                                        $record->unit,
                                    ]))
                                )
                                ->searchable(['code', 'name', 'size', 'unit'])
                                ->preload()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->live()
                                ->afterStateUpdated(function (mixed $state, Set $set): void {
                                    $set('unit', AtkItem::query()->find($state)?->unit ?? '');
                                })
                                ->required(),
                            TextInput::make('qty_requested')
                                ->label(__('Quantity'))
                                ->numeric()
                                ->minValue(0.01)
                                ->step(0.01)
                                ->required(),
                            TextInput::make('unit')
                                ->label(__('The item unit'))
                                ->default('pcs')
                                ->required()
                                ->maxLength(30),
                            TextInput::make('requester_note')
                                ->label(__('Note to GA'))
                                ->maxLength(500),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('request_number')
                    ->label(__('Number'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('request_date')
                    ->label(__('Date'))
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('requester.name')
                    ->label('Peminta')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('department.name')
                    ->label(__('Department'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('company.code')
                    ->label(__('Entity'))
                    ->badge()
                    ->color('info')
                    ->toggleable(),
                TextColumn::make('items_count')
                    ->label(__('Item'))
                    ->counts('items')
                    ->alignCenter(),
                TextColumn::make('need_type')
                    ->label(__('Needs'))
                    ->formatStateUsing(fn (string $state): string => $state === 'PROJECT' ? 'Project' : 'HO'),
                TextColumn::make('procurement.number')
                    ->label(__('Mounted homework'))
                    ->placeholder(__('Unmade'))
                    ->url(fn (AtkRequest $record): ?string => $record->procurement && Access::authors(Auth::user(), $record->procurement)
                        ? PurchaseRequests::getUrl(['permintaan' => $record->procurement->id]) : null),
                TextColumn::make('requested_items')
                    ->label(__('Things requested'))
                    ->extraCellAttributes(['style' => 'min-width: 14rem'])
                    ->state(fn (AtkRequest $record): array => static::requestedItemsList($record))
                    ->listWithLineBreaks()
                    ->wrap()
                    ->tooltip(fn (AtkRequest $record): string => static::requestedItemsSummary($record)),
                ViewColumn::make('item_progress')
                    ->label(__('Progress'))
                    ->extraCellAttributes(['style' => 'min-width: 26rem'])
                    ->view('filament.tables.columns.atk-request-progress')
                    ->tooltip(__('Requested is a total requirement, given the number of warehouses as per the entity and demand, received is the confirmed number of the requesters.')),
                TextColumn::make('next_step')
                    ->label(__('Next step'))
                    ->extraCellAttributes(['style' => 'min-width: 18rem'])
                    ->state(fn (AtkRequest $record): string => static::nextStep($record))
                    ->wrap(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => static::statusLabel($state))
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'partially_fulfilled', 'processing' => 'warning',
                        'cancelled' => 'danger',
                        default => 'info',
                    }),
                TextColumn::make('purpose')
                    ->label(__('Requirement'))
                    ->limit(45)
                    ->tooltip(fn (AtkRequest $record): string => $record->purpose),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('Status'))->options(static::statusOptions()),
                SelectFilter::make('department_id')
                    ->label(__('Department'))
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('permit_company_id')
                    ->label(__('Entity'))
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
                    ->label(__('Hand it over from the request warehouse'))
                    ->extraAttributes(['style' => 'min-height: 44px'])
                    ->icon(Heroicon::OutlinedArrowRightCircle)
                    ->color('primary')
                    ->visible(fn (AtkRequest $record): bool => static::canManage()
                        && in_array($record->status, ['submitted', 'processing', 'partially_fulfilled'], true)
                        && $record->items->contains(fn (AtkRequestItem $item): bool => $item->status !== 'cancelled' && $item->outstandingRequested() > 0))
                    ->form([
                        ...static::itemActionForm('Things to hand over'),
                        TextInput::make('quantity')
                            ->label(__('Number handed'))
                            ->helperText(__('Enter the total amount that was actually handed over at this stage.'))
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->required(),
                    ])
                    ->action(function (AtkRequest $record, array $data): void {
                        app(AtkWarehouseStockService::class)->issue(
                            static::requestItem($record, $data['request_item_id']),
                            (float) $data['quantity'],
                            Auth::user(),
                            $data['ga_note'] ?? null,
                        );
                        static::success('Hand-offs are accounted for.');
                    }),
                Action::make('create_pr')
                    ->label(__('Ask for less homework'))
                    ->extraAttributes(['style' => 'min-height: 44px'])
                    ->visible(fn (AtkRequest $record): bool => AtkProcurementService::canCreate(Auth::user())
                        && in_array($record->status, ['submitted', 'processing', 'partially_fulfilled'], true)
                        && ! $record->procurement
                        && $record->items->contains(fn (AtkRequestItem $item): bool => $item->status !== 'cancelled'
                            && $item->outstandingRequested() > static::availableStock($record, $item)))
                    ->requiresConfirmation()
                    ->modalHeading(__('Create a PR draft for stock shortages'))
                    ->modalDescription(__('Only an unfilled amount of available stock goes in. Draft is created on your behalf and follows the department\'s access and regular PR agreements. Stocks do not change until the item is delivered. Check and sign the draft before signing the agreement.'))
                    ->modalSubmitActionLabel(__('Create a PR draft'))
                    ->action(function (AtkRequest $record): void {
                        $draft = app(AtkProcurementService::class)->createDraft($record, Auth::user());
                        static::success('Draft PR '.$draft->number.' is available on the PR menu.');
                    }),
                Action::make('confirm_received')
                    ->label(__('Confirm item received'))
                    ->icon(Heroicon::OutlinedHandThumbUp)
                    ->color('success')
                    ->visible(fn (AtkRequest $record): bool => static::canConfirmReceipt($record))
                    ->form([
                        Select::make('request_item_id')
                            ->label(__('Received items'))
                            ->helperText(__('Select the items you have received. The system records all the amount that has been submitted and has not been confirmed.'))
                            ->options(fn (AtkRequest $record): array => static::receivableItems($record))
                            ->required(),
                    ])
                    ->action(function (AtkRequest $record, array $data): void {
                        app(AtkDepartmentStockService::class)->receive(
                            static::requestItem($record, $data['request_item_id']),
                            Auth::user(),
                        );
                        static::success('The department\'s acceptance and balance has been updated.');
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['requester', 'department', 'company', 'items.item', 'procurement']);
        $user = Auth::user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return static::canManage() ? $query : $query->where('requester_id', $user->id);
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasPermission('atk.requests') === true
            || Auth::user()?->hasPermission('atk.request') === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()?->hasPermission('atk.requests') === true;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny()
            && Auth::user()?->hasPermission('atk.request') === true;
    }

    public static function canManage(): bool
    {
        return Auth::user()?->hasPermission('atk.manage') === true;
    }

    public static function canChooseRequesterDepartment(): bool
    {
        $user = Auth::user();
        $user?->loadMissing('employee');

        return $user?->employee?->department_id === null
            && ($user?->is_admin === true || $user?->hasRole('system-admin') === true);
    }

    public static function hasMissingEmployeeDepartment(): bool
    {
        $user = Auth::user();
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
            Textarea::make('ga_note')->label(__('GA Notes'))->maxLength(500),
        ];
    }

    private static function outstandingItems(AtkRequest $record): array
    {
        return $record->items()->with('item')->get()
            ->filter(fn (AtkRequestItem $item): bool => $item->status !== 'cancelled' && $item->outstandingRequested() > 0)
            ->mapWithKeys(fn (AtkRequestItem $item): array => [
                $item->id => "{$item->item->name} (".number_format($item->outstandingRequested(), 0, ',', '.').')',
            ])->all();
    }

    private static function receivableItems(AtkRequest $record): array
    {
        return $record->items()->with('item')->get()
            ->filter(fn (AtkRequestItem $item): bool => $item->awaitingReceipt() > 0)
            ->mapWithKeys(fn (AtkRequestItem $item): array => [
                $item->id => "{$item->item->name} (waits: ".number_format($item->awaitingReceipt(), 0, ',', '.').')',
            ])->all();
    }

    private static function requestItem(AtkRequest $request, int|string $itemId): AtkRequestItem
    {
        return $request->items()->findOrFail($itemId);
    }

    private static function canConfirmReceipt(AtkRequest $record): bool
    {
        return (int) $record->requester_id === (int) Auth::id()
            && $record->items()->get()->contains(
                fn (AtkRequestItem $item): bool => $item->awaitingReceipt() > 0
            );
    }

    private static function statusOptions(): array
    {
        return [
            'submitted' => 'New',
            'processing' => 'In Process',
            'partially_fulfilled' => 'Partially fulfilled',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
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

    public static function itemProgressList(AtkRequest $request): array
    {
        return $request->items
            ->map(function (AtkRequestItem $item) use ($request): array {
                $available = static::availableStock($request, $item);
                $shortage = max(0, $item->outstandingRequested() - $available);

                return [
                    'name' => implode(' - ', array_filter([
                        $item->item?->name ?? 'Item',
                        $item->item?->size,
                    ])),
                    'shortage' => $shortage,
                    'metrics' => [
                        'Requested' => (float) $item->qty_requested,
                        'Issued' => (float) $item->qty_issued,
                        'Not yet issued' => $item->outstandingRequested(),
                        'Received' => (float) $item->qty_received,
                        'Available stock' => $available,
                        'Shortage' => $shortage,
                    ],
                ];
            })
            ->values()
            ->all();
    }

    private static function availableStock(AtkRequest $request, AtkRequestItem $item): float
    {
        if (! $item->item || ! in_array($request->company?->code, ['KPMOG', 'APCA'], true)) {
            return 0;
        }
        try {
            $stock = app(InventoryStock::class);

            return $stock->availableAtk($item->item, $stock->atkWarehouse($request)->id);
        } catch (ValidationException) {
            return 0;
        }
    }

    private static function nextStep(AtkRequest $record): string
    {
        if ($record->status === 'completed') {
            return 'Request complete.';
        }
        if ($record->status === 'cancelled') {
            return 'Request canceled.';
        }

        $awaitingReceipt = $record->items->sum(fn (AtkRequestItem $item): float => $item->awaitingReceipt());
        $outstanding = $record->items->sum(fn (AtkRequestItem $item): float => $item->outstandingRequested());

        if (static::canConfirmReceipt($record) && $awaitingReceipt > 0) {
            return 'Confirm the acceptance of the submitted items.';
        }

        if (static::canManage() && $outstanding > 0) {
            return $record->procurement
                ? 'PR '.$record->procurement->number.' related. Hand over available items; update stock when supplying is received.'
                : 'Issue available items. Create a purchase request for any shortage.';
        }

        if ($awaitingReceipt > 0) {
            return 'Waiting for the requester to confirm the reception.';
        }

        return 'Waiting for Process GA';
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'submitted' => __('New'),
            'processing' => __('In Process'),
            'partially_fulfilled' => __('Partially fulfilled'),
            'completed' => __('Completed'),
            'cancelled' => __('Cancelled'),
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    private static function success(string $title): void
    {
        Notification::make()->title($title)->success()->send();
    }
}
