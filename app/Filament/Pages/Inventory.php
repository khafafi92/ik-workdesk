<?php

namespace App\Filament\Pages;

use App\Enums\ItPermission;
use App\Filament\Resources\AtkItems\AtkItemResource;
use App\Filament\Resources\ItDevices\ItDeviceResource;
use App\Models\AtkItem;
use App\Models\Department;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryWarehouse;
use App\Models\ItDevice;
use App\Models\Scm\Procurement;
use App\Services\Inventory\InventoryAccess;
use App\Services\Inventory\InventoryCatalogue;
use App\Services\Inventory\InventoryStock;
use App\Services\Inventory\ProcurementReceipt;
use App\Services\Inventory\Warehouses;
use App\Services\ItInfrastructure\ItInfrastructureAccess;
use App\Services\Scm\Access;
use App\Services\Scm\Decimal;
use App\Services\Scm\Workflow;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use UnitEnum;

class Inventory extends Page
{
    use WithPagination;

    protected static ?string $slug = 'inventori';

    protected static ?string $title = 'Inventory';

    protected static ?string $navigationLabel = 'Inventory';

    protected static string|UnitEnum|null $navigationGroup = 'Inventori';

    protected string $view = 'filament.pages.inventory';

    #[Url(as: 'data')]
    public string $dataset = 'catalogue';

    #[Url(as: 'cari')]
    public string $search = '';

    public string $companyFilter = '';

    public string $needTypeFilter = '';

    public string $kindFilter = '';

    public string $warehouseFilter = '';

    public string $formMode = '';

    public array $catalogueForm = [];

    public array $stockForm = [];

    public array $allocation = [];

    public array $receiptForm = [];

    public array $receiptLines = [];

    public array $receiptNewItem = [];

    #[Locked]
    public int $receiptNewItemSource = 0;

    #[Locked]
    public int $catalogueId = 0;

    #[Locked]
    public int $catalogueVersion = 0;

    #[Locked]
    public int $allocationItemId = 0;

    #[Locked]
    public int $receiptRequestId = 0;

    #[Locked]
    public string $actionToken = '';

    public static function canAccess(): bool
    {
        return InventoryAccess::viewCatalogue(auth()->user()) || AtkItemResource::canViewAny();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->assertDataset($this->dataset);
    }

    private function assertDataset(string $dataset): void
    {
        abort_unless(in_array($dataset, ['catalogue', 'warehouses', 'balances', 'movements', 'assets', 'requests', 'receipts', 'stock'], true), 404);
        abort_if($dataset === 'receipts' && ! InventoryAccess::administer(auth()->user()), 403);
        abort_if($dataset === 'stock' && ! AtkItemResource::canViewAny(), 403);
        abort_if($dataset === 'assets' && ! InventoryAccess::viewAll(auth()->user()) && ! ItDeviceResource::canViewAny(), 403);
    }

    public function switchDataset(string $dataset): void
    {
        $this->assertDataset($dataset);
        $this->dataset = $dataset;
        $this->formMode = '';
        $this->receiptNewItemSource = 0;
        $this->receiptNewItem = [];
        $this->clearFilters();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'companyFilter', 'needTypeFilter', 'kindFilter', 'warehouseFilter'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'companyFilter', 'needTypeFilter', 'kindFilter', 'warehouseFilter');
        $this->resetPage();
    }

    public function closeForm(): void
    {
        $this->formMode = '';
        $this->receiptNewItemSource = 0;
        $this->receiptNewItem = [];
        $this->resetValidation();
    }

    private function notify(string $message): void
    {
        Notification::make()->title($message)->success()->send();
    }

    public function newItem(): void
    {
        abort_unless(collect(['atk', 'material', 'device'])->contains(fn ($kind) => InventoryAccess::editItem(auth()->user(), $kind)), 403);
        $this->catalogueId = 0;
        $this->catalogueVersion = 0;
        $this->catalogueForm = ['code' => '', 'name' => '', 'kind' => '', 'category' => '', 'unit' => '', 'brand' => '', 'model' => '', 'size' => '', 'specification' => '', 'is_active' => true];
        $this->formMode = 'catalogue';
        $this->resetValidation();
    }

    public function editItem(int $id): void
    {
        $item = InventoryItem::findOrFail($id);
        abort_unless(InventoryAccess::editItem(auth()->user(), $item->kind), 403);
        $this->catalogueId = $item->id;
        $this->catalogueVersion = $item->version;
        $this->catalogueForm = $item->only(['code', 'name', 'kind', 'category', 'unit', 'brand', 'model', 'size', 'specification', 'is_active']);
        $this->formMode = 'catalogue';
        $this->resetValidation();
    }

    public function saveItem(): void
    {
        app(InventoryCatalogue::class)->save(auth()->user(), $this->catalogueForm, $this->catalogueId ?: null, $this->catalogueVersion ?: null);
        $this->closeForm();
        $this->notify('Master of the Hidden goods.');
    }

    public function syncWarehouses(): void
    {
        abort_unless(InventoryAccess::administer(auth()->user()), 403);
        app(Warehouses::class)->syncProjects();
        $this->notify('Project warehouse is active.');
    }

    public function toggleWarehouse(int $id): void
    {
        abort_unless(InventoryAccess::administer(auth()->user()), 403);
        $warehouse = InventoryWarehouse::where('legacy_atk', false)->findOrFail($id);
        $warehouse->update(['is_active' => ! $warehouse->is_active]);
        $this->notify('The warehouse status is stored.');
    }

    public function newStock(): void
    {
        abort_unless(InventoryAccess::administer(auth()->user()), 403);
        $this->stockForm = ['operation' => 'incoming', 'balance_id' => '', 'warehouse_id' => '', 'inventory_item_id' => '', 'department_id' => '', 'destination_id' => '', 'quantity' => '', 'notes' => ''];
        $this->actionToken = (string) Str::uuid();
        $this->formMode = 'stock';
        $this->resetValidation();
    }

    public function openStock(int $id): void
    {
        $balance = InventoryBalance::with('warehouse')->findOrFail($id);
        abort_unless(! $balance->warehouse->legacy_atk && (InventoryAccess::administer(auth()->user()) || InventoryAccess::issueDepartment(auth()->user(), $balance)), 403);
        $this->stockForm = ['operation' => InventoryAccess::administer(auth()->user()) ? 'incoming' : 'outgoing', 'balance_id' => $id,
            'warehouse_id' => $balance->warehouse_id, 'inventory_item_id' => $balance->inventory_item_id, 'department_id' => $balance->department_id,
            'destination_id' => '', 'quantity' => '', 'notes' => ''];
        $this->actionToken = (string) Str::uuid();
        $this->formMode = 'stock';
        $this->resetValidation();
    }

    public function saveStock(): void
    {
        $this->validate(['stockForm.operation' => 'required|in:incoming,outgoing,transfer,department_transfer', 'stockForm.quantity' => 'required',
            'stockForm.notes' => 'required|string|max:2000']);
        DB::transaction(function (): void {
            $stock = app(InventoryStock::class);
            $data = $this->stockForm;
            if (! filled($data['balance_id'])) {
                abort_unless(InventoryAccess::administer(auth()->user()), 403);
                $this->validate(['stockForm.warehouse_id' => 'required|integer|exists:inventory_warehouses,id',
                    'stockForm.inventory_item_id' => 'required|integer|exists:inventory_items,id',
                    'stockForm.department_id' => 'required|integer|exists:departments,id']);
                abort_unless($data['operation'] === 'incoming', 422);
                $item = InventoryItem::findOrFail($data['inventory_item_id']);
                $warehouse = InventoryWarehouse::findOrFail($data['warehouse_id']);
                $data['balance_id'] = $stock->balance($warehouse, $item, (int) $data['department_id'])->id;
            }
            $stock->transact(auth()->user(), $data['operation'], [...$data, 'token' => $this->actionToken]);
        }, 3);
        $this->closeForm();
        $this->notify('The barn mutations are recorded.');
    }

    public function openAllocation(int $id): void
    {
        abort_unless(InventoryAccess::administer(auth()->user()) || auth()->user()->hasPermission('atk.manage'), 403);
        AtkItem::findOrFail($id);
        $this->allocationItemId = $id;
        $this->allocation = ['KPMOG' => '0', 'APCA' => '0'];
        $this->actionToken = (string) Str::uuid();
        $this->formMode = 'allocation';
        $this->resetValidation();
    }

    public function allocate(): void
    {
        app(InventoryStock::class)->allocateLegacy(auth()->user(), AtkItem::findOrFail($this->allocationItemId), $this->allocation, $this->actionToken);
        $this->closeForm();
        $this->notify('Old office supplies balance allocated to the entity warehouse.');
    }

    public function openReceipt(int $requestId): void
    {
        $request = Procurement::with(['items', 'cbe', 'purchaseOrder'])->findOrFail($requestId);
        abort_unless(InventoryAccess::administer(auth()->user()), 403);
        abort_unless($request->kind === 'PR' && in_array($request->status, ['selected', 'legal', 'completed'], true) && $request->cbe?->status === 'final' && $request->purchaseOrder, 422);
        $warehouse = app(Warehouses::class)->forContext($request->company, $request->need_type, $request->project_numbering_id);
        $this->receiptRequestId = $requestId;
        $this->receiptNewItemSource = 0;
        $this->receiptNewItem = [];
        $this->receiptForm = ['warehouse_id' => $warehouse->id, 'received_on' => today()->toDateString(), 'reference' => '', 'notes' => ''];
        $this->receiptLines = $request->items->map(fn ($line) => ['request_item_id' => $line->id, 'inventory_item_id' => $line->inventory_item_id ?? '', 'quantity' => '0'])->all();
        $this->actionToken = (string) Str::uuid();
        $this->formMode = 'receipt';
        $this->resetValidation();
    }

    public function receive(): void
    {
        $lines = collect($this->receiptLines)->filter(fn ($line) => Decimal::parse($line['quantity'] ?? '0', 3) > 0)->values()->all();
        app(ProcurementReceipt::class)->receive(auth()->user(), $this->receiptRequestId,
            [...$this->receiptForm, 'token' => $this->actionToken, 'lines' => $lines]);
        $this->closeForm();
        $this->notify('Receipts to the warehouse are noted, confirming the office supplies reception by the request continues through office supplies Request.');
    }

    public function newReceiptItem(int $requestItemId): void
    {
        abort_unless(InventoryAccess::administer(auth()->user()) && $this->formMode === 'receipt', 403);
        $source = Procurement::findOrFail($this->receiptRequestId)->items()->findOrFail($requestItemId);
        abort_if($source->inventory_item_id || ! collect($this->receiptLines)->contains('request_item_id', $source->id), 409);
        $this->receiptNewItemSource = $source->id;
        $this->receiptNewItem = ['code' => '', 'name' => $source->name, 'kind' => '', 'unit' => $source->unit,
            'category' => '', 'brand' => $source->brand ?? '', 'model' => '', 'size' => '',
            'specification' => $source->specification ?? '', 'is_active' => true];
        $this->resetValidation();
    }

    public function cancelReceiptItem(): void
    {
        $this->receiptNewItemSource = 0;
        $this->receiptNewItem = [];
        $this->resetValidation();
    }

    public function saveReceiptItem(): void
    {
        abort_unless(InventoryAccess::administer(auth()->user()) && $this->formMode === 'receipt' && $this->receiptNewItemSource, 403);
        $source = Procurement::findOrFail($this->receiptRequestId)->items()->findOrFail($this->receiptNewItemSource);
        abort_if($source->inventory_item_id, 409);
        $index = collect($this->receiptLines)->search(fn ($line) => (int) ($line['request_item_id'] ?? 0) === $source->id);
        abort_if($index === false || filled($this->receiptLines[$index]['inventory_item_id'] ?? null), 409);
        $data = [...$this->receiptNewItem, 'unit' => $source->unit, 'is_active' => true];
        $item = app(InventoryCatalogue::class)->save(auth()->user(), $data);
        $this->receiptLines[$index]['inventory_item_id'] = $item->id;
        $this->cancelReceiptItem();
        $this->notify('The master of goods is stored and selected.');
    }

    public static function procurementUrl(Procurement $request): ?string
    {
        $user = auth()->user();
        if (! in_array($request->status, ['draft', 'approval'], true) && Access::manages($user, $request)) {
            return ($request->need_type === 'HO' ? PurchasingOfficer::class : ScmProcurement::class)::getUrl(['permintaan' => $request->id]);
        }
        if (Access::authors($user, $request)) {
            return ($request->kind === 'SR' ? ServiceRequests::class : PurchaseRequests::class)::getUrl(['permintaan' => $request->id]);
        }

        return null;
    }

    private function balances()
    {
        return InventoryAccess::scopeBalances(InventoryBalance::query(), auth()->user());
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $this->assertDataset($this->dataset);
        $loadError = false;
        $rows = new LengthAwarePaginator([], 0, 20);
        $warehouseOptions = $itemOptions = $departmentOptions = $receiptItems = $receiptReceived = [];
        $receiptRequest = $allocationItem = null;
        $allocationAvailable = 0;
        try {
            $warehouses = InventoryWarehouse::with(['project', 'department'])->orderBy('name')->get();
            $warehouseOptions = $warehouses->where('legacy_atk', false)->filter(fn ($warehouse) => $warehouse->is_active && ($warehouse->kind === 'HO' || $warehouse->project?->is_active))
                ->mapWithKeys(fn ($warehouse) => [$warehouse->id => $warehouse->label()])->all();
            $itemOptions = InventoryItem::where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn ($item) => [$item->id => $item->code.' / '.$item->name.' / '.$item->unit])->all();
            $departmentOptions = Department::whereIn('id', auth()->user()->accessibleDepartmentIds())->orderBy('name')->pluck('name', 'id')->all();
            $matchWarehouse = function ($query): void {
                $query->when($this->companyFilter, fn ($q) => $q->where('company', $this->companyFilter))
                    ->when($this->needTypeFilter, fn ($q) => $q->where('kind', $this->needTypeFilter))
                    ->when($this->warehouseFilter, fn ($q) => $q->whereKey($this->warehouseFilter));
            };
            if ($this->dataset === 'catalogue') {
                $rows = InventoryItem::with('atkItem')->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$this->search.'%')->orWhere('code', 'like', '%'.$this->search.'%')))
                    ->when($this->kindFilter, fn ($q) => $q->where('kind', $this->kindFilter))->orderBy('name')->paginate(20);
            } elseif ($this->dataset === 'stock') {
                $rows = AtkItem::when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$this->search.'%')->orWhere('code', 'like', '%'.$this->search.'%')))->orderBy('name')->paginate(20);
            } elseif ($this->dataset === 'warehouses') {
                $query = InventoryWarehouse::with('project');
                $matchWarehouse($query);
                $rows = $query->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$this->search.'%')->orWhere('code', 'like', '%'.$this->search.'%')
                    ->orWhereHas('project', fn ($p) => $p->where('name', 'like', '%'.$this->search.'%'))))->orderBy('code')->paginate(20);
            } elseif ($this->dataset === 'balances') {
                $rows = $this->balances()->with(['item', 'warehouse.project', 'department'])
                    ->whereHas('warehouse', $matchWarehouse)
                    ->when($this->search !== '', fn ($q) => $q->whereHas('item', fn ($i) => $i->where(fn ($i) => $i->where('name', 'like', '%'.$this->search.'%')->orWhere('code', 'like', '%'.$this->search.'%'))))
                    ->orderBy('warehouse_id')->orderBy('inventory_item_id')->paginate(20);
            } elseif ($this->dataset === 'movements') {
                $rows = InventoryMovement::whereIn('balance_id', $this->balances()->select('id'))->with(['balance.item', 'balance.warehouse.project', 'balance.warehouse.department', 'balance.department', 'actor', 'receipt.request', 'receipt.purchaseOrder'])
                    ->whereHas('balance.warehouse', $matchWarehouse)
                    ->when($this->search !== '', fn ($q) => $q->whereHas('balance.item', fn ($i) => $i->where('name', 'like', '%'.$this->search.'%')))
                    ->orderByDesc('id')->paginate(20);
            } elseif ($this->dataset === 'assets') {
                $query = InventoryAccess::viewAll(auth()->user()) ? ItDevice::query()
                    : ItInfrastructureAccess::scope(ItDevice::query(), auth()->user(), 'managing_department_id', ItPermission::DevicesView, ItPermission::DevicesManage);
                $rows = $query->where('object_kind', '!=', 'room')->with(['inventoryItem', 'inventoryWarehouse.project'])
                    ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$this->search.'%')->orWhere('asset_number', 'like', '%'.$this->search.'%')->orWhere('serial_number', 'like', '%'.$this->search.'%')))
                    ->when($this->companyFilter, fn ($q) => $q->where('asset_number', 'like', '%/'.$this->companyFilter.'/%'))
                    ->when($this->warehouseFilter, fn ($q) => $q->where('inventory_warehouse_id', $this->warehouseFilter))
                    ->orderBy('asset_number')->paginate(20);
            } else {
                $query = Access::scope(Procurement::query(), auth()->user());
                if (InventoryAccess::viewAll(auth()->user()) && ! auth()->user()->isSystemAdministrator()) {
                    $query = Procurement::where(fn ($q) => $q->whereIn('id', $query->select('id'))->orWhereNotIn('status', ['draft', 'approval']));
                }
                if ($this->dataset === 'receipts') {
                    $query->where('kind', 'PR')->whereHas('purchaseOrder')->whereHas('cbe', fn ($q) => $q->where('status', 'final'));
                }
                $rows = $query->with(['requester', 'department', 'items', 'projectNumbering', 'sourceAtkRequest', 'cbe', 'purchaseOrder'])
                    ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', '%'.$this->search.'%')->orWhere('title', 'like', '%'.$this->search.'%')->orWhereHas('items', fn ($i) => $i->where('name', 'like', '%'.$this->search.'%'))))
                    ->when($this->companyFilter, fn ($q) => $q->where('company', $this->companyFilter))
                    ->when($this->needTypeFilter, fn ($q) => $q->where('need_type', $this->needTypeFilter))
                    ->when($this->kindFilter, fn ($q) => $q->where('kind', $this->kindFilter))->orderByDesc('id')->paginate(20);
            }
            if ($this->formMode === 'allocation') {
                abort_unless(InventoryAccess::administer(auth()->user()) || auth()->user()->hasPermission('atk.manage'), 403);
                $allocationItem = AtkItem::findOrFail($this->allocationItemId);
                $allocationAvailable = app(InventoryStock::class)->availableAtk($allocationItem, app(Warehouses::class)->legacyAtk()->id);
            }
            if ($this->formMode === 'receipt') {
                $receiptRequest = Procurement::with(['items', 'purchaseOrder'])->findOrFail($this->receiptRequestId);
                abort_unless(InventoryAccess::administer(auth()->user()), 403);
                $receiptItems = $receiptRequest->items->keyBy('id');
                $receiptReceived = InventoryMovement::whereIn('request_item_id', $receiptItems->keys())->where('type', 'pr_receipt')->selectRaw('request_item_id, SUM(quantity_milli) as total')->groupBy('request_item_id')->pluck('total', 'request_item_id');
            }
        } catch (QueryException $exception) {
            report($exception);
            $loadError = true;
        }

        return compact('rows', 'loadError', 'warehouseOptions', 'itemOptions', 'departmentOptions', 'receiptRequest', 'receiptItems', 'receiptReceived', 'allocationItem', 'allocationAvailable')
            + ['canAdminister' => InventoryAccess::administer(auth()->user()), 'viewAll' => InventoryAccess::viewAll(auth()->user()),
                'canViewAssets' => InventoryAccess::viewAll(auth()->user()) || ItDeviceResource::canViewAny(),
                'canViewStock' => AtkItemResource::canViewAny(), 'stockUrl' => AtkItemResource::canViewAny() ? AtkItemResource::getUrl() : null,
                'canCreateAsset' => Gate::allows('create', ItDevice::class),
                'editableKinds' => collect(['atk' => 'ATK', 'material' => 'Material/barang', 'device' => 'Perangkat/aset'])->filter(fn ($label, $kind) => InventoryAccess::editItem(auth()->user(), $kind))->all(),
                'statuses' => Workflow::STATUSES];
    }
}
