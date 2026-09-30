<?php

namespace Tests\Feature;

use App\Exports\AtkItemImportTemplateExport;
use App\Exports\AtkReportExport;
use App\Models\AtkCategory;
use App\Models\AtkDepartmentBalance;
use App\Models\AtkItem;
use App\Models\AtkRequest;
use App\Models\AtkRequestItem;
use App\Models\AtkUnit;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\PermitCompany;
use App\Models\User;
use App\Services\AtkDepartmentStockService;
use App\Services\AtkItemImportService;
use App\Services\AtkWarehouseStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class AtkManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_issue_receive_and_usage_keep_each_stock_ledger_correct(): void
    {
        [$requester, $department] = $this->requester();
        $ga = User::factory()->create(['is_admin' => true]);
        $item = AtkItem::query()->create([
            'code' => 'ATK-001',
            'name' => 'Pulpen Hitam',
            'unit' => 'Pcs',
            'current_stock' => 6,
            'is_active' => true,
        ]);
        $request = $this->request($requester, $department);
        $requestItem = AtkRequestItem::query()->create([
            'atk_request_id' => $request->id,
            'atk_item_id' => $item->id,
            'qty_requested' => 10,
            'unit' => 'Pcs',
        ]);

        app(AtkWarehouseStockService::class)->issue($requestItem, 6, $ga);
        $this->assertSame('0.00', $item->fresh()->current_stock);
        $this->assertSame('6.00', $requestItem->fresh()->qty_issued);
        $this->assertSame('partially_fulfilled', $request->fresh()->status);

        app(AtkDepartmentStockService::class)->receive($requestItem, $requester);
        $this->assertSame('6.00', AtkDepartmentBalance::query()->firstOrFail()->qty_available);
        $this->assertSame('6.00', $requestItem->fresh()->qty_received);

        app(AtkDepartmentStockService::class)->use($department, $item->id, 3, $requester, 'Kebutuhan rapat');
        $this->assertSame('3.00', AtkDepartmentBalance::query()->firstOrFail()->qty_available);
        $this->assertDatabaseCount('atk_stock_movements', 1);
        $this->assertDatabaseCount('atk_department_stock_movements', 2);
        $this->assertDatabaseCount('atk_usage_transactions', 1);
    }

    public function test_waiting_procurement_does_not_block_available_items_and_completed_requires_received_quantity(): void
    {
        [$requester, $department] = $this->requester();
        $ga = User::factory()->create(['is_admin' => true]);
        $available = AtkItem::query()->create(['code' => 'ATK-002', 'name' => 'Buku', 'unit' => 'Pcs', 'current_stock' => 5, 'is_active' => true]);
        $unavailable = AtkItem::query()->create(['code' => 'ATK-003', 'name' => 'Papan Tulis', 'unit' => 'Unit', 'current_stock' => 0, 'is_active' => true]);
        $request = $this->request($requester, $department);
        $availableItem = AtkRequestItem::query()->create(['atk_request_id' => $request->id, 'atk_item_id' => $available->id, 'qty_requested' => 5, 'unit' => 'Pcs']);
        $unavailableItem = AtkRequestItem::query()->create(['atk_request_id' => $request->id, 'atk_item_id' => $unavailable->id, 'qty_requested' => 1, 'unit' => 'Unit']);

        $warehouse = app(AtkWarehouseStockService::class);
        $warehouse->markWaitingProcurement($unavailableItem, $ga, 'Menunggu pembelian.');
        $warehouse->issue($availableItem, 5, $ga);

        $this->assertSame('waiting_procurement', $unavailableItem->fresh()->status);
        $this->assertSame('partially_fulfilled', $request->fresh()->status);

        app(AtkDepartmentStockService::class)->receive($availableItem, $requester);
        $this->assertSame('partially_fulfilled', $request->fresh()->status);

        $warehouse->incoming($unavailable, 1, $ga, 'Pembelian baru.');
        $warehouse->markReady($unavailableItem, $ga);
        $warehouse->issue($unavailableItem, 1, $ga);
        app(AtkDepartmentStockService::class)->receive($unavailableItem, $requester);

        $this->assertSame('completed', $request->fresh()->status);
    }

    public function test_department_usage_cannot_make_balance_negative(): void
    {
        [$requester, $department] = $this->requester();
        $item = AtkItem::query()->create(['code' => 'ATK-004', 'name' => 'Kertas', 'unit' => 'Rim', 'is_active' => true]);
        AtkDepartmentBalance::query()->create(['department_id' => $department->id, 'atk_item_id' => $item->id, 'qty_available' => 2]);

        $this->expectException(ValidationException::class);

        app(AtkDepartmentStockService::class)->use($department, $item->id, 3, $requester);
    }

    public function test_request_numbers_are_department_scoped_and_receiving_is_idempotent(): void
    {
        [$requester, $department] = $this->requester();
        $anotherDepartment = Department::query()->create(['code' => 'GA', 'name' => 'General Affairs', 'is_active' => true]);
        $ga = User::factory()->create(['is_admin' => true]);
        $item = AtkItem::query()->create(['code' => 'ATK-005', 'name' => 'Map', 'unit' => 'Pcs', 'current_stock' => 2, 'is_active' => true]);

        $this->assertSame('ATK/IT/'.now()->format('Y').'/0001', AtkRequest::generateRequestNumber($department));
        AtkRequest::query()->create([
            'request_number' => AtkRequest::generateRequestNumber($department),
            'request_date' => today(),
            'requester_id' => $requester->id,
            'department_id' => $department->id,
            'status' => 'submitted',
            'created_by' => $requester->id,
        ]);
        $this->assertSame('ATK/IT/'.now()->format('Y').'/0002', AtkRequest::generateRequestNumber($department));
        $this->assertSame('ATK/GA/'.now()->format('Y').'/0001', AtkRequest::generateRequestNumber($anotherDepartment));

        $request = $this->request($requester, $department);
        $requestItem = AtkRequestItem::query()->create(['atk_request_id' => $request->id, 'atk_item_id' => $item->id, 'qty_requested' => 2, 'unit' => 'Pcs']);
        app(AtkWarehouseStockService::class)->issue($requestItem, 2, $ga);
        app(AtkDepartmentStockService::class)->receive($requestItem, $requester);
        app(AtkDepartmentStockService::class)->receive($requestItem, $requester);

        $this->assertSame('2.00', AtkDepartmentBalance::query()->firstOrFail()->qty_available);
        $this->assertDatabaseCount('atk_department_stock_movements', 1);
    }

    public function test_atk_panel_pages_render_for_an_authorized_manager(): void
    {
        $manager = User::factory()->create(['is_admin' => true]);

        foreach ([
            '/panel/atk-dashboard',
            '/panel/atk-requests',
            '/panel/atk-requests/create',
            '/panel/atk-requirement-summary',
            '/panel/atk-reports',
            '/panel/atk-categories',
            '/panel/atk-units',
            '/panel/atk-items',
            '/panel/atk-department-balances',
            '/panel/atk-department-stock-movements',
            '/panel/atk-stock-movements',
            '/panel/atk-usage-transactions',
            '/panel/atk-request-histories',
        ] as $url) {
            $this->actingAs($manager)->get($url)->assertOk();
        }
    }

    public function test_requester_is_limited_to_request_and_department_stock_menus(): void
    {
        [$requester] = $this->requester();
        $permission = Permission::query()->where('code', 'atk.request')->firstOrFail();
        $requester->directPermissions()->attach($permission);

        $this->actingAs($requester)->get('/panel/atk-requests')->assertOk();
        $this->actingAs($requester)->get('/panel/atk-department-balances')->assertOk();
        $this->actingAs($requester)->get('/panel/atk-items')->assertForbidden();
        $this->actingAs($requester)->get('/panel/atk-dashboard')->assertForbidden();
        $this->actingAs($requester)->get('/panel/atk-reports')->assertForbidden();
    }

    public function test_ga_can_import_items_and_stock_with_ledger_entries(): void
    {
        $ga = User::factory()->create(['is_admin' => true]);
        Storage::fake('local');
        Storage::disk('local')->put('atk-imports/items.csv', implode("\n", [
            'code,name,category,unit,minimum_stock,current_stock,is_active',
            'ATK-IMPORT,Pulpen Biru,Alat Tulis,pcs,10,25,1',
        ]));

        $result = app(AtkItemImportService::class)->import('atk-imports/items.csv', $ga);

        $this->assertSame(['created' => 1, 'updated' => 0, 'stockAdjusted' => 1], $result);
        $this->assertDatabaseHas('atk_items', ['code' => 'ATK-IMPORT', 'current_stock' => 25]);
        $this->assertDatabaseCount('atk_stock_movements', 1);

        Storage::disk('local')->put('atk-imports/items-update.csv', implode("\n", [
            'code,name,category,unit,minimum_stock,current_stock,is_active',
            'ATK-IMPORT,Pulpen Biru,Alat Tulis,pcs,10,20,1',
        ]));
        app(AtkItemImportService::class)->import('atk-imports/items-update.csv', $ga);

        $this->assertDatabaseHas('atk_items', ['code' => 'ATK-IMPORT', 'current_stock' => 20]);
        $this->assertDatabaseCount('atk_stock_movements', 2);
    }

    public function test_atk_report_is_available_as_an_excel_download(): void
    {
        Excel::fake();

        Excel::download(new AtkReportExport, 'laporan-atk-semua-data.xlsx');

        Excel::assertDownloaded('laporan-atk-semua-data.xlsx', fn (AtkReportExport $export): bool => $export instanceof AtkReportExport);
    }

    public function test_excel_template_can_be_imported_by_ga(): void
    {
        $ga = User::factory()->create(['is_admin' => true]);
        Storage::fake('local');
        Excel::store(new AtkItemImportTemplateExport, 'atk-imports/template.xlsx', 'local');

        $result = app(AtkItemImportService::class)->import('atk-imports/template.xlsx', $ga);

        $this->assertSame(2, $result['created']);
        $this->assertDatabaseHas('atk_items', ['code' => 'ATK-001', 'current_stock' => 50]);
        $this->assertDatabaseHas('atk_items', ['code' => 'ATK-002', 'current_stock' => 20]);
        $this->assertSame('PCS', AtkItem::query()->where('code', 'ATK-001')->firstOrFail()->unitMaster->name);
    }

    public function test_atk_categories_and_units_are_managed_separately_from_item_stock(): void
    {
        $this->assertDatabaseHas('atk_units', ['name' => 'PCS', 'is_active' => true]);
        $this->assertDatabaseHas('atk_units', ['name' => 'Rim', 'is_active' => true]);

        $category = AtkCategory::query()->create(['name' => 'Arsip', 'is_active' => true]);
        $unit = AtkUnit::query()->create(['name' => 'Set', 'is_active' => true]);
        $item = AtkItem::query()->create([
            'code' => 'ATK-UNIT-001',
            'name' => 'Map arsip',
            'category' => $category->name,
            'atk_category_id' => $category->id,
            'unit' => $unit->name,
            'atk_unit_id' => $unit->id,
            'is_active' => true,
        ]);

        $this->assertSame('Arsip', $item->categoryMaster->name);
        $this->assertSame('Set', $item->unitMaster->name);
    }

    public function test_atk_request_records_the_requesting_entity(): void
    {
        [$requester, $department] = $this->requester();
        $company = PermitCompany::query()->where('code', 'APCA')->firstOrFail();
        $request = AtkRequest::query()->create([
            'request_number' => AtkRequest::generateRequestNumber($department),
            'request_date' => today(),
            'requester_id' => $requester->id,
            'department_id' => $department->id,
            'permit_company_id' => $company->id,
            'status' => 'submitted',
            'created_by' => $requester->id,
        ]);

        $this->assertSame('APCA', $request->company->code);
        $this->assertDatabaseHas('atk_requests', [
            'id' => $request->id,
            'permit_company_id' => $company->id,
        ]);
    }

    private function requester(): array
    {
        $department = Department::query()->create(['code' => 'IT', 'name' => 'Information Technology', 'is_active' => true]);
        $user = User::factory()->create();
        Employee::query()->create(['user_id' => $user->id, 'department_id' => $department->id, 'name' => 'Requester ATK', 'is_active' => true]);

        return [$user, $department];
    }

    private function request(User $requester, Department $department): AtkRequest
    {
        $company = PermitCompany::query()->where('code', 'KPMOG')->firstOrFail();

        return AtkRequest::query()->create([
            'request_number' => AtkRequest::generateRequestNumber($department),
            'request_date' => today(),
            'requester_id' => $requester->id,
            'department_id' => $department->id,
            'permit_company_id' => $company->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'created_by' => $requester->id,
        ]);
    }
}
