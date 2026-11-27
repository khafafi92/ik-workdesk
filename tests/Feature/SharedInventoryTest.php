<?php

namespace Tests\Feature;

use App\Enums\ItDeviceType;
use App\Filament\Pages\Inventory;
use App\Filament\Pages\PurchaseRequests;
use App\Models\AtkDepartmentBalance;
use App\Models\AtkItem;
use App\Models\AtkRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryReceipt;
use App\Models\ItDevice;
use App\Models\Permission;
use App\Models\PermitCompany;
use App\Models\Scm\Cbe;
use App\Models\Scm\Procurement;
use App\Models\Scm\ProjectNumbering;
use App\Models\Scm\PurchaseOrder;
use App\Models\Scm\Vendor;
use App\Models\User;
use App\Services\AtkDepartmentStockService;
use App\Services\AtkProcurementService;
use App\Services\AtkWarehouseStockService;
use App\Services\Inventory\AssetRegistration;
use App\Services\Inventory\InventoryAccess;
use App\Services\Inventory\InventoryBackfill;
use App\Services\Inventory\InventoryCatalogue;
use App\Services\Inventory\InventoryStock;
use App\Services\Inventory\ProcurementReceipt;
use App\Services\Inventory\Warehouses;
use Database\Seeders\ScmSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SharedInventoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->seed(ScmSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->department = Department::create(['code' => 'INVENTORY-QA', 'name' => 'Inventori QA']);
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    private function actor(array $permissions): User
    {
        $user = User::factory()->create(['is_admin' => false]);
        Employee::create(['user_id' => $user->id, 'department_id' => $this->department->id, 'name' => $user->name, 'is_active' => true]);
        $user->directPermissions()->attach(Permission::whereIn('code', $permissions)->pluck('id'));

        return $user;
    }

    private function item(string $kind = 'material'): InventoryItem
    {
        return app(InventoryCatalogue::class)->save($this->admin, ['code' => 'ITEM-'.Str::ulid(), 'name' => 'Barang QA '.$kind,
            'kind' => $kind, 'unit' => 'PCS', 'brand' => 'Merek QA', 'model' => 'Model QA', 'specification' => 'Spesifikasi QA', 'is_active' => true]);
    }

    private function request(InventoryItem $item, string $company = 'KPMOG', string $kind = 'HO', int $quantity = 5000): Procurement
    {
        $project = $kind === 'PROJECT' ? ProjectNumbering::firstOrFail()->id : null;
        $request = Procurement::create(['number' => 'PR-QA-'.Str::ulid(), 'kind' => 'PR', 'company' => $company, 'need_type' => $kind,
            'project_numbering_id' => $project, 'date' => today(), 'requester_id' => $this->admin->id,
            'department_id' => $this->department->id, 'status' => 'selected', 'title' => 'Pengadaan QA', 'reason' => 'Kebutuhan QA']);
        $request->items()->create(['inventory_item_id' => $item->id, 'position' => 1, 'name' => $item->name,
            'specification' => 'Spesifikasi PR yang disetujui', 'quantity_milli' => $quantity, 'unit' => $item->unit]);
        $vendor = Vendor::create(['code' => 'V-QA-'.Str::ulid(), 'name' => 'Vendor QA', 'provider_type' => 'barang', 'is_active' => true, 'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);
        $cbe = Cbe::create(['request_id' => $request->id, 'number' => 'CBE-QA-'.Str::ulid(), 'date' => today(), 'status' => 'final',
            'selected_vendor_id' => $vendor->id, 'prepared_by' => $this->admin->id, 'snapshot' => ['quotes' => [], 'request' => ['number' => $request->number]]]);
        $number = 'PO-QA-'.Str::ulid();
        PurchaseOrder::create(['request_id' => $request->id, 'cbe_id' => $cbe->id, 'company' => $company, 'number' => $number,
            'number_key' => strtolower($number), 'issued_at' => today(), 'delivery_terms' => 'Gudang', 'delivery_time' => 'Hari ini',
            'payment_terms' => 'Termin', 'snapshot' => ['lines' => []], 'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);

        return $request;
    }

    private function receive(Procurement $request, string $quantity, ?string $token = null): InventoryReceipt
    {
        $warehouse = app(Warehouses::class)->forContext($request->company, $request->need_type, $request->project_numbering_id);
        $line = $request->items()->sole();

        return app(ProcurementReceipt::class)->receive($this->admin, $request->id, ['token' => $token ?? (string) Str::uuid(),
            'warehouse_id' => $warehouse->id, 'received_on' => today()->toDateString(), 'reference' => 'SJ-QA',
            'lines' => [['request_item_id' => $line->id, 'inventory_item_id' => $line->inventory_item_id, 'quantity' => $quantity]]]);
    }

    private function rejects(callable $action): void
    {
        try {
            $action();
        } catch (ValidationException|HttpException $exception) {
            $this->assertTrue(true);

            return;
        }
        $this->fail('Aksi harus ditolak.');
    }

    public function test_backfill_preserves_unallocated_atk_stock_and_is_idempotent(): void
    {
        $id = DB::table('atk_items')->insertGetId(['code' => 'OLD-QA', 'name' => 'Stok lama QA', 'unit' => 'PCS', 'current_stock' => 12.5,
            'actual_stock' => 11, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        app(InventoryBackfill::class)->run();
        $atk = AtkItem::findOrFail($id);
        $balance = InventoryBalance::where('inventory_item_id', $atk->inventory_item_id)->sole();
        $this->assertSame('12.50', $atk->current_stock);
        $this->assertSame('11.00', $atk->actual_stock);
        $this->assertSame(12500, $balance->quantity_milli);
        $this->assertNull($balance->warehouse->company);
        $this->assertTrue($balance->warehouse->legacy_atk);
        app(InventoryBackfill::class)->run();
        $this->assertSame(1, InventoryItem::where('name', 'Stok lama QA')->count());
        $this->assertSame(1, InventoryMovement::where('balance_id', $balance->id)->count());
    }

    public function test_legacy_atk_allocation_keeps_companies_separate_and_preserves_total(): void
    {
        $item = $this->item('atk');
        $atk = $item->atkItem;
        app(AtkWarehouseStockService::class)->incoming($atk, 10, $this->admin);
        $token = (string) Str::uuid();
        app(InventoryStock::class)->allocateLegacy($this->admin, $atk, ['KPMOG' => '6', 'APCA' => '4'], $token);
        app(InventoryStock::class)->allocateLegacy($this->admin, $atk, ['KPMOG' => '6', 'APCA' => '4'], $token);
        $this->assertSame('10.00', $atk->fresh()->current_stock);
        $stock = app(InventoryStock::class);
        $this->assertSame(6.0, $stock->availableAtk($atk, app(Warehouses::class)->forContext('KPMOG', 'HO', null)->id));
        $this->assertSame(4.0, $stock->availableAtk($atk, app(Warehouses::class)->forContext('APCA', 'HO', null)->id));
        $this->assertSame(0.0, $stock->availableAtk($atk, app(Warehouses::class)->legacyAtk()->id));
        $this->rejects(fn () => $stock->allocateLegacy($this->admin, $atk, ['KPMOG' => '1'], (string) Str::uuid()));
        $this->assertSame('10.00', $atk->fresh()->current_stock);
    }

    public function test_atk_issue_and_requester_confirmation_stay_separate_after_entity_mapping(): void
    {
        $item = $this->item('atk');
        $warehouse = app(Warehouses::class)->forContext('KPMOG', 'HO', null);
        app(AtkWarehouseStockService::class)->incoming($item->atkItem, 5, $this->admin, 'Stok KPMOG QA', $warehouse);
        $requester = $this->actor(['atk.request']);
        $request = AtkRequest::create(['request_number' => 'ATK-QA', 'request_date' => today(), 'requester_id' => $requester->id,
            'department_id' => $this->department->id, 'permit_company_id' => PermitCompany::where('code', 'KPMOG')->sole()->id,
            'need_type' => 'HO', 'purpose' => 'QA', 'status' => 'submitted', 'created_by' => $requester->id]);
        $line = $request->items()->create(['atk_item_id' => $item->atkItem->id, 'qty_requested' => 5, 'unit' => 'PCS']);
        app(AtkWarehouseStockService::class)->issue($line, 2, $this->admin);
        $this->assertSame('3.00', $item->atkItem->fresh()->current_stock);
        $this->assertSame(0, AtkDepartmentBalance::count());
        app(AtkDepartmentStockService::class)->receive($line, $requester);
        app(AtkDepartmentStockService::class)->receive($line->fresh(), $requester);
        $this->assertSame('2.00', AtkDepartmentBalance::sole()->qty_available);
        $this->assertSame('2.00', $line->fresh()->qty_received);
        $request->update(['permit_company_id' => PermitCompany::where('code', 'APCA')->sole()->id]);
        $this->rejects(fn () => app(AtkWarehouseStockService::class)->issue($line->fresh(), 1, $this->admin));
        $this->assertSame('3.00', $item->atkItem->fresh()->current_stock);
    }

    public function test_partial_pr_receipts_are_idempotent_and_cannot_exceed_approved_quantity(): void
    {
        $item = $this->item();
        $request = $this->request($item);
        $token = (string) Str::uuid();
        $receipt = $this->receive($request, '2', $token);
        $this->assertSame($receipt->id, $this->receive($request, '2', $token)->id);
        $this->receive($request, '3');
        $this->assertSame(5000, InventoryBalance::sole()->quantity_milli);
        $this->assertSame(2, InventoryReceipt::count());
        $this->rejects(fn () => $this->receive($request, '0.001'));
        $this->assertSame(5000, InventoryBalance::sole()->quantity_milli);
        $this->assertSame('Spesifikasi PR yang disetujui', $request->items()->sole()->specification);
    }

    public function test_repeated_receipt_rejects_changed_data_instead_of_claiming_it_was_saved(): void
    {
        $item = $this->item();
        $request = $this->request($item);
        $source = $request->items()->sole();
        $warehouse = app(Warehouses::class)->forContext('KPMOG', 'HO', null);
        $service = app(ProcurementReceipt::class);
        $data = ['token' => (string) Str::uuid(), 'warehouse_id' => $warehouse->id, 'received_on' => today()->toDateString(),
            'reference' => 'SJ-RETRY', 'notes' => 'Penerimaan pertama',
            'lines' => [['request_item_id' => $source->id, 'inventory_item_id' => $item->id, 'quantity' => '2']]];
        $receipt = $service->receive($this->admin, $request->id, $data);
        $this->assertSame($receipt->id, $service->receive($this->admin, $request->id, [...$data, 'lines' => [[...$data['lines'][0], 'quantity' => '2,000']]])->id);
        $this->rejects(fn () => $service->receive($this->admin, $request->id, [...$data, 'lines' => [[...$data['lines'][0], 'quantity' => '3']]]));
        $this->rejects(fn () => $service->receive($this->admin, $request->id, [...$data, 'reference' => 'SJ-LAIN']));
        $this->assertSame(1, InventoryReceipt::count());
        $this->assertSame(2000, InventoryBalance::sole()->quantity_milli);
    }

    public function test_po_receipt_by_inventory_officer_moves_to_department_and_can_be_distributed(): void
    {
        $item = $this->item();
        $request = $this->request($item);
        $warehouse = app(Warehouses::class)->forContext('KPMOG', 'HO', null);
        $officer = $this->actor(['inventory.manage']);
        $processor = $this->actor(['scm.process']);
        $data = ['token' => (string) Str::uuid(), 'warehouse_id' => $warehouse->id, 'received_on' => today()->toDateString(),
            'reference' => 'SJ-1', 'lines' => [['request_item_id' => $request->items()->sole()->id, 'inventory_item_id' => $item->id, 'quantity' => '3']]];
        $this->rejects(fn () => app(ProcurementReceipt::class)->receive($processor, $request->id, $data));
        $receipt = app(ProcurementReceipt::class)->receive($officer, $request->id, $data);
        $this->assertSame($request->purchaseOrder->id, $receipt->purchase_order_id);
        $source = InventoryBalance::sole();
        $stock = app(InventoryStock::class);
        $transfer = ['balance_id' => $source->id, 'quantity' => '2', 'notes' => 'Penyerahan ke IT', 'token' => (string) Str::uuid()];
        $stock->transact($officer, 'department_transfer', $transfer);
        $stock->transact($officer, 'department_transfer', $transfer);
        $destination = InventoryBalance::whereHas('warehouse', fn ($q) => $q->where('department_id', $this->department->id))->sole();
        $this->assertSame(1000, $source->fresh()->quantity_milli);
        $this->assertSame(2000, $destination->quantity_milli);
        $departmentOfficer = $this->actor(['inventory.department-issue']);
        $issue = ['balance_id' => $destination->id, 'quantity' => '1', 'notes' => 'Diserahkan kepada user', 'token' => (string) Str::uuid()];
        $this->rejects(fn () => $stock->transact($departmentOfficer, 'incoming', $issue));
        $this->rejects(fn () => $stock->transact($departmentOfficer, 'outgoing', [...$issue, 'balance_id' => $source->id]));
        $otherDepartment = Department::create(['code' => 'OTHER-QA', 'name' => 'Departemen lain', 'is_active' => true]);
        $otherUser = User::factory()->create(['is_admin' => false]);
        Employee::create(['user_id' => $otherUser->id, 'department_id' => $otherDepartment->id, 'name' => $otherUser->name, 'is_active' => true]);
        $otherUser->directPermissions()->attach(Permission::where('code', 'inventory.department-issue')->sole());
        $this->rejects(fn () => $stock->transact($otherUser, 'outgoing', $issue));
        $stock->transact($departmentOfficer, 'outgoing', $issue);
        $this->assertSame(1000, $destination->fresh()->quantity_milli);
        Livewire::actingAs($departmentOfficer)->test(Inventory::class)->call('switchDataset', 'balances')
            ->assertSee('Record distribution')->call('openStock', $destination->id)->assertSee('Distribusi / stok keluar');
        Livewire::actingAs($departmentOfficer)->test(Inventory::class)->call('switchDataset', 'receipts')->assertForbidden();
    }

    public function test_receipt_requires_po(): void
    {
        $request = $this->request($this->item());
        $request->purchaseOrder()->delete();
        $this->rejects(fn () => $this->receive($request, '1'));
        $this->assertSame(0, InventoryReceipt::count());
    }

    public function test_project_receipt_is_kept_in_project_and_rejects_department_as_initial_destination(): void
    {
        $request = $this->request($this->item(), kind: 'PROJECT');
        $main = app(Warehouses::class)->forContext('KPMOG', 'PROJECT', $request->project_numbering_id);
        $department = app(Warehouses::class)->forDepartment($main, $this->department->fresh());
        $line = $request->items()->sole();
        $data = ['token' => (string) Str::uuid(), 'warehouse_id' => $department->id, 'received_on' => today()->toDateString(),
            'reference' => 'SJ Project', 'lines' => [['request_item_id' => $line->id, 'inventory_item_id' => $line->inventory_item_id, 'quantity' => '1']]];
        $this->rejects(fn () => app(ProcurementReceipt::class)->receive($this->admin, $request->id, $data));
        $data['warehouse_id'] = $main->id;
        app(ProcurementReceipt::class)->receive($this->admin, $request->id, $data);
        $this->assertSame($main->id, InventoryBalance::sole()->warehouse_id);
    }

    public function test_unallocated_atk_cannot_be_issued_or_converted_before_entity_mapping(): void
    {
        $item = $this->item('atk');
        app(AtkWarehouseStockService::class)->incoming($item->atkItem, 5, $this->admin, 'Saldo lama QA');
        $request = AtkRequest::create(['request_number' => 'ATK-LEGACY-QA', 'request_date' => today(), 'requester_id' => $this->admin->id,
            'department_id' => $this->department->id, 'permit_company_id' => PermitCompany::where('code', 'KPMOG')->sole()->id,
            'need_type' => 'HO', 'purpose' => 'QA', 'status' => 'submitted', 'created_by' => $this->admin->id]);
        $line = $request->items()->create(['atk_item_id' => $item->atkItem->id, 'qty_requested' => 3, 'unit' => 'PCS']);
        $this->rejects(fn () => app(AtkWarehouseStockService::class)->issue($line, 1, $this->admin));
        $this->rejects(fn () => app(AtkProcurementService::class)->createDraft($request, $this->admin));
        $this->assertSame(0, Procurement::count());
        app(InventoryStock::class)->allocateLegacy($this->admin, $item->atkItem, ['KPMOG' => '2', 'APCA' => '3'], (string) Str::uuid());
        $draft = app(AtkProcurementService::class)->createDraft($request->fresh(), $this->admin);
        $this->assertSame(1000, $draft->items()->sole()->quantity_milli);
        $this->assertSame('5.00', $item->atkItem->fresh()->current_stock);
    }

    public function test_receipts_reject_wrong_company_project_unapproved_requests_and_sr(): void
    {
        $item = $this->item();
        $request = $this->request($item, kind: 'PROJECT');
        $line = $request->items()->sole();
        $data = ['token' => (string) Str::uuid(), 'warehouse_id' => app(Warehouses::class)->forContext('APCA', 'PROJECT', $request->project_numbering_id)->id,
            'received_on' => today()->toDateString(), 'reference' => 'SJ-QA', 'lines' => [['request_item_id' => $line->id, 'inventory_item_id' => $item->id, 'quantity' => '1']]];
        $this->rejects(fn () => app(ProcurementReceipt::class)->receive($this->admin, $request->id, $data));
        $data['warehouse_id'] = app(Warehouses::class)->forContext('KPMOG', 'HO', null)->id;
        $this->rejects(fn () => app(ProcurementReceipt::class)->receive($this->admin, $request->id, $data));
        $request->update(['status' => 'submitted']);
        $this->rejects(fn () => $this->receive($request, '1'));
        $request->update(['status' => 'selected', 'kind' => 'SR']);
        $this->rejects(fn () => $this->receive($request, '1'));
        $this->assertSame(0, InventoryReceipt::count());
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_project_warehouse_identity_survives_project_rename(): void
    {
        $project = ProjectNumbering::firstOrFail();
        $warehouse = app(Warehouses::class)->forContext('KPMOG', 'PROJECT', $project->id);
        $project->update(['name' => 'Project QA berganti nama']);
        $this->assertSame($warehouse->id, app(Warehouses::class)->forContext('KPMOG', 'PROJECT', $project->id)->id);
        $this->assertStringContainsString('Project QA berganti nama', $warehouse->fresh()->label());
    }

    public function test_transfer_is_atomic_and_cannot_cross_companies_or_overdraw(): void
    {
        $item = $this->item();
        $request = $this->request($item);
        $this->receive($request, '5');
        $source = InventoryBalance::sole();
        $stock = app(InventoryStock::class);
        $data = ['balance_id' => $source->id, 'quantity' => '2', 'notes' => 'Transfer QA', 'token' => (string) Str::uuid(),
            'destination_id' => app(Warehouses::class)->forContext('APCA', 'HO', null)->id];
        $this->rejects(fn () => $stock->transact($this->admin, 'transfer', $data));
        $this->assertSame(5000, $source->fresh()->quantity_milli);
        $data['destination_id'] = app(Warehouses::class)->forContext('KPMOG', 'PROJECT', ProjectNumbering::firstOrFail()->id)->id;
        $stock->transact($this->admin, 'transfer', $data);
        $stock->transact($this->admin, 'transfer', $data);
        $this->assertSame(5000, (int) InventoryBalance::sum('quantity_milli'));
        $this->assertSame(3000, $source->fresh()->quantity_milli);
        $this->rejects(fn () => $stock->transact($this->admin, 'outgoing', [...$data, 'quantity' => '4', 'token' => (string) Str::uuid()]));
    }

    public function test_read_all_is_read_only_and_department_scope_still_applies_to_processors(): void
    {
        $item = $this->item();
        $a = $this->request($item);
        $b = $this->request($item, 'APCA', 'PROJECT');
        $other = Department::create(['code' => 'INV-OTHER', 'name' => 'Departemen QA lain']);
        $b->update(['department_id' => $other->id]);
        $this->receive($a, '1');
        $this->receive($b, '1');
        $scm = $this->actor(['scm.process-apca']);
        $this->assertSame(0, InventoryAccess::scopeBalances(InventoryBalance::query(), $scm)->count());
        $reader = $this->actor(['inventory.view-all']);
        $this->assertSame(2, InventoryAccess::scopeBalances(InventoryBalance::query(), $reader)->count());
        Livewire::actingAs($reader)->test(Inventory::class)->call('switchDataset', 'balances')
            ->assertViewHas('rows', fn ($rows) => $rows->total() === 2)
            ->set('companyFilter', 'APCA')->assertViewHas('rows', fn ($rows) => $rows->total() === 1 && $rows->first()->warehouse->company === 'APCA')
            ->call('newStock')->assertForbidden();
        $this->rejects(fn () => app(InventoryCatalogue::class)->save($reader, ['code' => 'DENIED', 'name' => 'Tidak boleh', 'kind' => 'material', 'unit' => 'PCS', 'is_active' => true]));
    }

    public function test_pr_master_selection_populates_fields_and_keeps_saved_snapshot(): void
    {
        $item = $this->item();
        $requester = $this->actor(['scm.request']);
        $page = Livewire::actingAs($requester)->test(PurchaseRequests::class)->call('newDraft')
            ->set('draft.items.0.inventory_item_id', $item->id)->assertSet('draft.items.0.name', $item->name)
            ->assertSet('draft.items.0.unit', $item->unit)->call('saveDraft')->assertHasNoErrors();
        $line = Procurement::sole()->items()->sole();
        $this->assertSame($item->id, $line->inventory_item_id);
        app(InventoryCatalogue::class)->save($this->admin, [...$item->toArray(), 'name' => 'Nama master diperbarui'], $item->id, $item->version);
        $this->assertSame('Barang QA material', $line->fresh()->name);
    }

    public function test_device_registration_consumes_one_unit_without_creating_another_catalogue(): void
    {
        $item = $this->item('device');
        $this->receive($this->request($item, quantity: 2000), '2');
        $balance = InventoryBalance::sole();
        $device = ItDevice::create(['code' => 'DEVICE-QA', 'name' => 'Laptop pemakai QA', 'device_type' => ItDeviceType::Laptop,
            'inventory_item_id' => $item->id, 'asset_number' => '00001/KPMOG/IT/2026', 'managing_department_id' => $this->department->id, 'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);
        DB::transaction(fn () => app(AssetRegistration::class)->consume($this->admin, $device, $balance->id));
        $this->assertSame(1000, $balance->fresh()->quantity_milli);
        $this->assertSame(1, InventoryItem::count());
        $this->assertNotNull($device->fresh()->inventory_movement_id);
        $this->rejects(fn () => DB::transaction(fn () => app(AssetRegistration::class)->consume($this->admin, $device->fresh(), $balance->id)));
        $this->assertSame(1000, $balance->fresh()->quantity_milli);
    }

    public function test_inventory_forms_create_catalogue_receive_goods_and_render_each_dataset(): void
    {
        Livewire::actingAs($this->admin)->test(Inventory::class)->call('newItem')
            ->set('catalogueForm', ['code' => 'LIVEWIRE-QA', 'name' => 'Barang formulir QA', 'kind' => 'material', 'unit' => 'PCS', 'is_active' => true])
            ->call('saveItem')->assertHasNoErrors()->assertSee('Barang formulir QA');
        $item = InventoryItem::where('code', 'LIVEWIRE-QA')->sole();
        $request = $this->request($item);
        $page = Livewire::test(Inventory::class)->call('switchDataset', 'requests')->call('openReceipt', $request->id)
            ->assertSee('Street mail number/receivement evidence')->set('receiptForm.reference', 'SJ-LIVEWIRE-QA')
            ->set('receiptLines.0.quantity', '2')->call('receive')->assertHasNoErrors();
        $this->assertSame(2000, InventoryBalance::sole()->quantity_milli);
        foreach (['catalogue', 'warehouses', 'balances', 'movements', 'assets', 'stock', 'requests', 'receipts'] as $dataset) {
            $page->call('switchDataset', $dataset)->assertHasNoErrors()->assertDontSee('Inventory has not been loaded. Try reloading data.');
        }
        $page->call('switchDataset', 'balances')->call('openStock', InventoryBalance::sole()->id)->assertSee('References and reasons for mutations');
    }

    public function test_receipt_can_create_and_select_new_master_without_adding_stock_until_receipt(): void
    {
        $oldItem = $this->item();
        $request = $this->request($oldItem);
        $source = $request->items()->sole();
        $source->update(['inventory_item_id' => null, 'name' => 'Valve baru', 'unit' => 'unit']);
        $officer = $this->actor(['inventory.manage']);
        $page = Livewire::actingAs($officer)->test(Inventory::class)
            ->call('openReceipt', $request->id)
            ->set('receiptForm.reference', 'SJ-VALVE')
            ->set('receiptLines.0.quantity', '1')
            ->set('receiptLines.0.inventory_item_id', $oldItem->id)->assertDontSee('Create catalogue item for Valve baru')
            ->set('receiptLines.0.inventory_item_id', '')->assertSee('Create catalogue item for Valve baru')
            ->call('newReceiptItem', $source->id)
            ->assertSet('receiptNewItem.name', 'Valve baru')
            ->assertSet('receiptNewItem.unit', 'unit')
            ->set('receiptNewItem.code', 'VALVE-NEW')
            ->set('receiptNewItem.kind', 'material')
            ->call('saveReceiptItem')->assertHasNoErrors()
            ->assertSet('receiptForm.reference', 'SJ-VALVE')->assertSet('receiptLines.0.quantity', '1');
        $newItem = InventoryItem::where('code', 'VALVE-NEW')->sole();
        $this->assertSame('unit', $newItem->unit);
        $this->assertDatabaseMissing('inventory_movements', ['request_item_id' => $source->id]);
        $page->assertSet('receiptLines.0.inventory_item_id', $newItem->id)->call('receive')->assertHasNoErrors();
        $this->assertSame($newItem->id, $source->fresh()->inventory_item_id);
        $this->assertDatabaseHas('inventory_movements', ['request_item_id' => $source->id, 'type' => 'pr_receipt', 'quantity_milli' => 1000]);
    }

    public function test_catalogue_permissions_stale_versions_and_units_are_enforced(): void
    {
        $item = $this->item('atk');
        $data = [...$item->toArray(), 'name' => 'Nama baru QA'];
        $updated = app(InventoryCatalogue::class)->save($this->admin, $data, $item->id, $item->version);
        $this->assertSame('Nama baru QA', $item->atkItem->fresh()->name);
        $this->rejects(fn () => app(InventoryCatalogue::class)->save($this->admin, $data, $item->id, $item->version));
        app(AtkWarehouseStockService::class)->incoming($updated->atkItem, 3, $this->admin, 'Stok QA', app(Warehouses::class)->forContext('KPMOG', 'HO', null));
        $this->rejects(fn () => app(InventoryCatalogue::class)->save($this->admin, [...$updated->toArray(), 'unit' => 'BOX'], $updated->id, $updated->version));
        $this->assertSame('PCS', $updated->fresh()->unit);
        $reader = $this->actor(['inventory.view-all']);
        $this->rejects(fn () => app(AtkWarehouseStockService::class)->incoming($updated->atkItem, 1, $reader));
    }

    public function test_allocation_and_stock_forms_keep_balances_and_retries_consistent(): void
    {
        $atk = $this->item('atk')->atkItem;
        app(AtkWarehouseStockService::class)->incoming($atk, 3, $this->admin, 'Saldo lama QA');
        $page = Livewire::actingAs($this->admin)->test(Inventory::class)->call('openAllocation', $atk->id)
            ->assertSee('Office supplies balance mapping')->set('allocation.KPMOG', '2')->set('allocation.APCA', '1')
            ->call('allocate')->assertHasNoErrors();
        $this->assertSame('3.00', $atk->fresh()->current_stock);
        $item = $this->item();
        $warehouse = app(Warehouses::class)->forContext('KPMOG', 'HO', null);
        $page->call('newStock')->set('stockForm', ['operation' => 'incoming', 'balance_id' => '', 'warehouse_id' => $warehouse->id,
            'inventory_item_id' => $item->id, 'department_id' => $this->department->id, 'destination_id' => '', 'quantity' => '4', 'notes' => 'Saldo formulir QA'])
            ->call('saveStock')->assertHasNoErrors()->call('saveStock')->assertHasNoErrors();
        $balance = InventoryBalance::where('inventory_item_id', $item->id)->sole();
        $this->assertSame(4000, $balance->quantity_milli);
        $target = app(Warehouses::class)->forContext('KPMOG', 'PROJECT', ProjectNumbering::firstOrFail()->id);
        $page->call('openStock', $balance->id)->set('stockForm.operation', 'transfer')->set('stockForm.destination_id', $target->id)
            ->set('stockForm.quantity', '1')->set('stockForm.notes', 'Transfer formulir QA')->call('saveStock')->assertHasNoErrors();
        $this->assertSame(3000, $balance->fresh()->quantity_milli);
        $this->assertSame(4000, (int) InventoryBalance::where('inventory_item_id', $item->id)->sum('quantity_milli'));
    }
}
