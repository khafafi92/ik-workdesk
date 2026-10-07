<?php

namespace Tests\Feature;

use App\Exports\AtkItemImportTemplateExport;
use App\Exports\AtkReportExport;
use App\Filament\Resources\AtkItems\Pages\CreateAtkItem;
use App\Filament\Resources\AtkItems\Pages\EditAtkItem;
use App\Filament\Resources\AtkItems\Pages\ListAtkItems;
use App\Filament\Resources\AtkRequests\Pages\CreateAtkRequest;
use App\Filament\Resources\AtkUsageTransactions\Pages\CreateAtkUsageTransaction;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class AtkManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_atk_inventory_uses_the_new_columns_without_changing_system_stock(): void
    {
        $manager = User::factory()->create(['is_admin' => true]);
        $unit = AtkUnit::query()->create(['name' => 'Ream', 'is_active' => true]);

        Livewire::actingAs($manager)
            ->test(CreateAtkItem::class)
            ->fillForm([
                'code' => 'ATK-A4-001',
                'name' => 'A4 Paper',
                'size' => 'A4',
                'atk_unit_id' => $unit->id,
                'current_stock' => 23,
                'actual_stock' => 19,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = AtkItem::query()->where('code', 'ATK-A4-001')->sole();

        Livewire::actingAs($manager)
            ->test(ListAtkItems::class)
            ->assertSee('No.')
            ->assertSee('Item Name')
            ->assertSee('Size')
            ->assertSee('Quantity')
            ->assertSee('Actual')
            ->assertSee('Unit')
            ->assertCanSeeTableRecords([$item])
            ->searchTable('ATK-A4-001')
            ->assertCanSeeTableRecords([$item]);

        Livewire::actingAs($manager)
            ->test(EditAtkItem::class, ['record' => $item->getRouteKey()])
            ->fillForm(['actual_stock' => 17])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('23.00', $item->fresh()->current_stock);
        $this->assertSame('17.00', $item->fresh()->actual_stock);
    }

    public function test_atk_stock_columns_hide_unneeded_decimal_zeros(): void
    {
        $manager = User::factory()->create(['is_admin' => true]);
        $wholeItem = AtkItem::query()->create([
            'code' => 'ATK-WHOLE-001',
            'name' => 'Barang jumlah bulat',
            'unit' => 'PCS',
            'current_stock' => 1,
            'actual_stock' => 1,
            'is_active' => true,
        ]);
        $fractionalItem = AtkItem::query()->create([
            'code' => 'ATK-FRACTION-001',
            'name' => 'Barang jumlah pecahan',
            'unit' => 'PCS',
            'current_stock' => 1.5,
            'actual_stock' => 1.5,
            'is_active' => true,
        ]);

        Livewire::actingAs($manager)
            ->test(ListAtkItems::class)
            ->assertCanSeeTableRecords([$wholeItem, $fractionalItem])
            ->assertSeeText('1')
            ->assertSeeText('1.5')
            ->assertDontSeeText('1.00')
            ->assertDontSeeText('1.50');
    }

    public function test_authorized_requester_can_submit_a_complete_atk_request_from_the_form(): void
    {
        [$requester, $department] = $this->requester();
        $permission = Permission::query()->where('code', 'atk.request')->firstOrFail();
        $requester->directPermissions()->attach($permission);
        $item = AtkItem::query()->create([
            'code' => 'ATK-CREATE-001',
            'name' => 'Pulpen Hitam',
            'unit' => 'PCS',
            'is_active' => true,
        ]);
        $company = PermitCompany::query()->where('code', 'KPMOG')->firstOrFail();

        Livewire::actingAs($requester)
            ->test(CreateAtkRequest::class)
            ->fillForm([
                'purpose' => 'Kebutuhan operasional IT',
                'permit_company_id' => $company->id,
                'items' => [[
                    'atk_item_id' => $item->id,
                    'qty_requested' => 5,
                    'unit' => 'PCS',
                    'requester_note' => 'Untuk IT',
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('atk_requests', [
            'department_id' => $department->id,
            'permit_company_id' => $company->id,
            'purpose' => 'Kebutuhan operasional IT',
            'status' => 'submitted',
        ]);
        $this->assertDatabaseHas('atk_request_items', [
            'atk_item_id' => $item->id,
            'qty_requested' => 5,
            'unit' => 'PCS',
        ]);
    }

    public function test_ga_receives_a_database_notification_when_a_department_submits_an_atk_request(): void
    {
        [$requester] = $this->requester();
        $requester->directPermissions()->attach(
            Permission::query()->where('code', 'atk.request')->firstOrFail()
        );
        $gaDepartment = Department::query()->create([
            'code' => 'GA',
            'name' => 'General Affairs',
            'is_active' => true,
        ]);
        $ga = User::factory()->create();
        Employee::query()->create([
            'user_id' => $ga->id,
            'department_id' => $gaDepartment->id,
            'name' => 'Petugas GA',
            'is_active' => true,
        ]);
        $ga->directPermissions()->attach(
            Permission::query()->where('code', 'atk.manage')->firstOrFail()
        );
        $item = AtkItem::query()->create([
            'code' => 'ATK-NOTIFICATION-001',
            'name' => 'Pulpen Biru',
            'unit' => 'PCS',
            'is_active' => true,
        ]);
        $company = PermitCompany::query()->where('code', 'KPMOG')->firstOrFail();

        Livewire::actingAs($requester)
            ->test(CreateAtkRequest::class)
            ->fillForm([
                'purpose' => 'Kebutuhan operasional',
                'permit_company_id' => $company->id,
                'items' => [[
                    'atk_item_id' => $item->id,
                    'qty_requested' => 5,
                    'unit' => 'PCS',
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $notification = $ga->notifications()->latest()->first();

        $this->assertNotNull($notification);
        $this->assertSame('Permintaan ATK baru', $notification->data['title']);
        $this->assertStringContainsString('Pulpen Biru 5 PCS', $notification->data['body']);
    }

    public function test_system_administrator_without_employee_can_select_the_requesting_department(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
        $department = Department::query()->create([
            'code' => 'IT',
            'name' => 'Information Technology',
            'is_active' => true,
        ]);
        $item = AtkItem::query()->create([
            'code' => 'ATK-ADMIN-001',
            'name' => 'Kertas',
            'unit' => 'RIM',
            'is_active' => true,
        ]);
        $company = PermitCompany::query()->where('code', 'KPMOG')->firstOrFail();

        Livewire::actingAs($administrator)
            ->test(CreateAtkRequest::class)
            ->fillForm([
                'purpose' => 'Kebutuhan administrasi',
                'requester_department_id' => $department->id,
                'permit_company_id' => $company->id,
                'items' => [[
                    'atk_item_id' => $item->id,
                    'qty_requested' => 2,
                    'unit' => 'RIM',
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('atk_requests', [
            'requester_id' => $administrator->id,
            'department_id' => $department->id,
            'status' => 'submitted',
        ]);
    }

    public function test_atk_dashboard_and_request_list_show_who_requested_each_item(): void
    {
        [$requester, $department] = $this->requester();
        $manager = User::factory()->create(['is_admin' => true]);
        $request = $this->request($requester, $department);
        $item = AtkItem::query()->create([
            'code' => 'ATK-VISIBLE-001',
            'name' => 'Pulpen Hitam',
            'unit' => 'PCS',
            'is_active' => true,
        ]);
        AtkRequestItem::query()->create([
            'atk_request_id' => $request->id,
            'atk_item_id' => $item->id,
            'qty_requested' => 5,
            'unit' => 'PCS',
        ]);

        $this->actingAs($manager)
            ->get('/panel/atk-dashboard')
            ->assertOk()
            ->assertSeeText('Permintaan yang perlu ditindak')
            ->assertSeeText($requester->name)
            ->assertSeeText('Pulpen Hitam')
            ->assertSeeText('Barang dan progres')
            ->assertSeeText('Perlu ditinjau GA');

        $this->actingAs($manager)
            ->get('/panel/atk-requests')
            ->assertOk()
            ->assertSeeText('Progres barang')
            ->assertSeeText('Pulpen Hitam')
            ->assertSeeText('Serahkan sisa barang dari Gudang Utama jika stok tersedia.');
    }

    public function test_atk_requirement_summary_shows_request_context_and_delivery_progress(): void
    {
        [$requester, $department] = $this->requester();
        $manager = User::factory()->create(['is_admin' => true]);
        $request = $this->request($requester, $department);
        $item = AtkItem::query()->create([
            'code' => 'ATK-SUMMARY-001',
            'name' => 'Kertas A4',
            'unit' => 'RIM',
            'is_active' => true,
        ]);
        AtkRequestItem::query()->create([
            'atk_request_id' => $request->id,
            'atk_item_id' => $item->id,
            'qty_requested' => 10,
            'qty_issued' => 6,
            'qty_received' => 2,
            'unit' => 'RIM',
            'status' => 'issued',
            'ga_note' => 'Sisa sedang disiapkan.',
        ]);

        $this->actingAs($manager)
            ->get('/panel/atk-requirement-summary')
            ->assertOk()
            ->assertSeeText('Kebutuhan ATK yang belum selesai')
            ->assertSeeText('Permintaan terbuka')
            ->assertSeeText('Belum diserahkan')
            ->assertSeeText('Menunggu konfirmasi')
            ->assertSeeText($request->request_number)
            ->assertSeeText($requester->name)
            ->assertSeeText($department->name)
            ->assertSeeText('Diminta')
            ->assertSeeText('Diserahkan')
            ->assertSeeText('Diterima')
            ->assertSeeText('Perlu diserahkan dan dikonfirmasi')
            ->assertSeeText('Catatan GA: Sisa sedang disiapkan.');
    }

    public function test_outgoing_letter_form_explains_the_draft_and_issuance_flow(): void
    {
        $manager = User::factory()->create(['is_admin' => true]);

        $this->actingAs($manager)
            ->get('/panel/outgoing-letters/create')
            ->assertOk()
            ->assertSeeText('Tentukan profil dan identitas surat')
            ->assertSeeText('Lengkapi tujuan dan isi')
            ->assertSeeText('Tinjau nomor dan simpan draft')
            ->assertSeeText('Nomor menjadi final dan dicadangkan hanya saat surat diterbitkan.');
    }

    public function test_requester_can_see_the_receipt_action_after_ga_issues_an_item(): void
    {
        [$requester, $department] = $this->requester();
        $permission = Permission::query()->where('code', 'atk.request')->firstOrFail();
        $requester->directPermissions()->attach($permission);
        $ga = User::factory()->create(['is_admin' => true]);
        $item = AtkItem::query()->create([
            'code' => 'ATK-RECEIPT-001',
            'name' => 'Map Dokumen',
            'unit' => 'PCS',
            'current_stock' => 2,
            'is_active' => true,
        ]);
        $request = $this->request($requester, $department);
        $requestItem = AtkRequestItem::query()->create([
            'atk_request_id' => $request->id,
            'atk_item_id' => $item->id,
            'qty_requested' => 2,
            'unit' => 'PCS',
        ]);

        app(AtkWarehouseStockService::class)->issue($requestItem, 2, $ga);

        $notification = $requester->notifications()->latest()->first();

        $this->assertNotNull($notification);
        $this->assertSame('Barang ATK telah diserahkan', $notification->data['title']);
        $this->assertStringContainsString('Map Dokumen sejumlah 2 PCS', $notification->data['body']);

        $this->actingAs($requester)
            ->get('/panel/atk-requests')
            ->assertOk()
            ->assertSeeText('Konfirmasi barang diterima')
            ->assertSeeText('Konfirmasi penerimaan barang yang sudah diserahkan.')
            ->assertSeeText('Map Dokumen: diminta 2, diserahkan 2, belum diserahkan 0, diterima 0');
    }

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

    public function test_requester_can_record_atk_usage_from_the_create_form(): void
    {
        [$requester, $department] = $this->requester();
        $requester->directPermissions()->attach(
            Permission::query()
                ->whereIn('code', ['atk.request', 'atk.usage'])
                ->pluck('id')
        );
        $item = AtkItem::query()->create([
            'code' => 'ATK-USAGE-FORM-001',
            'name' => 'Pulpen Hitam',
            'unit' => 'PCS',
            'is_active' => true,
        ]);
        AtkDepartmentBalance::query()->create([
            'department_id' => $department->id,
            'atk_item_id' => $item->id,
            'qty_available' => 3,
        ]);

        Livewire::actingAs($requester)
            ->test(CreateAtkUsageTransaction::class)
            ->fillForm([
                'atk_item_id' => $item->id,
                'usage_date' => today()->toDateString(),
                'qty_used' => 1,
                'purpose' => 'Kebutuhan administrasi',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('atk_usage_transactions', [
            'department_id' => $department->id,
            'atk_item_id' => $item->id,
            'qty_used' => 1,
            'purpose' => 'Kebutuhan administrasi',
            'used_by' => $requester->id,
        ]);
        $this->assertSame('2.00', AtkDepartmentBalance::query()->firstOrFail()->qty_available);
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

    public function test_atk_import_accepts_uploaded_file_instances_from_filament(): void
    {
        $ga = User::factory()->create(['is_admin' => true]);

        $file = UploadedFile::fake()->createWithContent('items.csv', implode("\n", [
            'code,name,category,unit,minimum_stock,current_stock,is_active',
            'ATK-IMPORT,Pulpen Biru,Alat Tulis,pcs,10,25,1',
        ]));

        $result = app(AtkItemImportService::class)->import($file, $ga);

        $this->assertSame(['created' => 1, 'updated' => 0, 'stockAdjusted' => 1], $result);
        $this->assertDatabaseHas('atk_items', ['code' => 'ATK-IMPORT', 'current_stock' => 25]);
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

    public function test_legacy_atk_import_does_not_clear_size_or_actual_stock(): void
    {
        $ga = User::factory()->create(['is_admin' => true]);
        $item = AtkItem::query()->create([
            'code' => 'ATK-LEGACY',
            'name' => 'A4 Paper',
            'size' => 'A4',
            'unit' => 'Ream',
            'current_stock' => 10,
            'actual_stock' => 8,
            'is_active' => true,
        ]);
        Storage::fake('local');
        Storage::disk('local')->put('atk-imports/legacy.csv', implode("\n", [
            'code,name,category,unit,minimum_stock,current_stock,is_active',
            'ATK-LEGACY,A4 Paper,Kertas,Ream,5,12,1',
        ]));

        app(AtkItemImportService::class)->import('atk-imports/legacy.csv', $ga);

        $this->assertSame('A4', $item->fresh()->size);
        $this->assertSame('8.00', $item->fresh()->actual_stock);
        $this->assertSame('12.00', $item->fresh()->current_stock);
    }

    public function test_atk_report_is_available_as_an_excel_download(): void
    {
        Excel::fake();

        Excel::download(new AtkReportExport, 'laporan-atk-semua-data.xlsx');

        Excel::assertDownloaded('laporan-atk-semua-data.xlsx', fn (AtkReportExport $export): bool => $export instanceof AtkReportExport);
    }

    public function test_atk_report_page_explains_the_monthly_summary(): void
    {
        $manager = User::factory()->create(['is_admin' => true]);

        $this->actingAs($manager)
            ->get('/panel/atk-reports')
            ->assertOk()
            ->assertSeeText('Rekap Bulanan')
            ->assertSeeText('bulan permintaan')
            ->assertSeeText('Permintaan yang dibatalkan tidak dihitung.');
    }

    public function test_department_receipt_summary_excludes_requests_that_have_not_been_received(): void
    {
        [$requester, $department] = $this->requester();
        $item = AtkItem::query()->create([
            'code' => 'ATK-REPORT-001',
            'name' => 'Pulpen Hitam',
            'unit' => 'PCS',
            'is_active' => true,
        ]);
        $receivedRequest = $this->request($requester, $department);
        AtkRequestItem::query()->create([
            'atk_request_id' => $receivedRequest->id,
            'atk_item_id' => $item->id,
            'qty_requested' => 10,
            'qty_issued' => 10,
            'qty_received' => 10,
            'unit' => 'PCS',
            'status' => 'received',
        ]);
        $pendingRequest = $this->request($requester, $department);
        AtkRequestItem::query()->create([
            'atk_request_id' => $pendingRequest->id,
            'atk_item_id' => $item->id,
            'qty_requested' => 5,
            'unit' => 'PCS',
            'status' => 'pending',
        ]);

        $sheet = (new AtkReportExport)->sheets()[1];

        $this->assertSame([
            'Departemen', 'Barang ATK', 'Jumlah Diterima', 'Satuan',
        ], $sheet->headings());
        $this->assertSame([[
            $department->name,
            'Pulpen Hitam',
            10.0,
            'PCS',
        ]], $sheet->collection()->all());
    }

    public function test_atk_report_summarizes_requests_by_month_and_excludes_cancelled_requests(): void
    {
        [$requester, $department] = $this->requester();
        $item = AtkItem::query()->create([
            'code' => 'ATK-MONTHLY-001',
            'name' => 'Kertas A4',
            'size' => 'A4',
            'unit' => 'REAM',
            'is_active' => true,
        ]);

        $firstRequest = $this->request($requester, $department);
        $firstRequest->update(['request_date' => '2026-01-05']);
        AtkRequestItem::query()->create([
            'atk_request_id' => $firstRequest->id,
            'atk_item_id' => $item->id,
            'qty_requested' => 5,
            'qty_issued' => 3,
            'qty_received' => 1,
            'unit' => 'REAM',
        ]);

        $secondRequest = $this->request($requester, $department);
        $secondRequest->update(['request_date' => '2026-01-20']);
        AtkRequestItem::query()->create([
            'atk_request_id' => $secondRequest->id,
            'atk_item_id' => $item->id,
            'qty_requested' => 3,
            'qty_issued' => 2,
            'qty_received' => 1,
            'unit' => 'REAM',
        ]);

        $nextMonthRequest = $this->request($requester, $department);
        $nextMonthRequest->update(['request_date' => '2026-02-02']);
        AtkRequestItem::query()->create([
            'atk_request_id' => $nextMonthRequest->id,
            'atk_item_id' => $item->id,
            'qty_requested' => 10,
            'unit' => 'REAM',
        ]);

        $cancelledRequest = $this->request($requester, $department);
        $cancelledRequest->update(['request_date' => '2026-01-25', 'status' => 'cancelled']);
        AtkRequestItem::query()->create([
            'atk_request_id' => $cancelledRequest->id,
            'atk_item_id' => $item->id,
            'qty_requested' => 100,
            'unit' => 'REAM',
        ]);

        $sheet = (new AtkReportExport(
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-01-31'),
        ))->sheets()[0];

        $this->assertSame([
            'Bulan Permintaan', 'Departemen', 'Entitas', 'Kode Barang', 'Barang ATK', 'Ukuran', 'Satuan',
            'Jumlah Permintaan', 'Jumlah Diminta', 'Jumlah Diserahkan', 'Jumlah Diterima',
        ], $sheet->headings());
        $this->assertSame([[
            '2026-01',
            $department->name,
            'KPMOG',
            'ATK-MONTHLY-001',
            'Kertas A4',
            'A4',
            'REAM',
            2,
            8.0,
            5.0,
            2.0,
        ]], $sheet->collection()->all());
    }

    public function test_excel_template_can_be_imported_by_ga(): void
    {
        $ga = User::factory()->create(['is_admin' => true]);
        Storage::fake('local');
        $sheet = (new AtkItemImportTemplateExport)->sheets()[0];
        $this->assertSame(['Item Name', 'Size', 'Quantity', 'Actual', 'Unit'], $sheet->headings());
        Excel::store(new AtkItemImportTemplateExport, 'atk-imports/template.xlsx', 'local');

        $result = app(AtkItemImportService::class)->import('atk-imports/template.xlsx', $ga);

        $this->assertSame(2, $result['created']);
        $a4 = AtkItem::query()->where(['name' => 'A4 Paper', 'size' => 'A4'])->sole();
        $f4 = AtkItem::query()->where(['name' => 'A4 Paper', 'size' => 'F4'])->sole();
        $this->assertMatchesRegularExpression('/^ATK-\d{5}$/', $a4->code);
        $this->assertSame('23.00', $a4->current_stock);
        $this->assertSame('19.00', $a4->actual_stock);
        $this->assertSame('2.00', $f4->current_stock);
        $this->assertSame('3.00', $f4->actual_stock);
        $this->assertSame('Ream', $a4->unitMaster->name);
    }

    public function test_atk_import_accepts_fractional_quantity_from_the_simplified_template(): void
    {
        $ga = User::factory()->create(['is_admin' => true]);
        Storage::fake('local');
        Storage::disk('local')->put('atk-imports/fraction.csv', implode("\n", [
            'item_name,size,quantity,actual,unit',
            'A3 Paper,A3,1/2,1/2,Ream',
        ]));

        app(AtkItemImportService::class)->import('atk-imports/fraction.csv', $ga);

        $item = AtkItem::query()->where(['name' => 'A3 Paper', 'size' => 'A3'])->sole();
        $this->assertSame('0.50', $item->current_stock);
        $this->assertSame('0.50', $item->actual_stock);
        $this->assertNull($item->category);
        $this->assertNull($item->minimum_stock);
    }

    public function test_atk_import_converts_rim_to_ream_and_parses_quantities_with_rim_suffix(): void
    {
        $ga = User::factory()->create(['is_admin' => true]);
        Storage::fake('local');
        Storage::disk('local')->put('atk-imports/ream.csv', implode("\n", [
            'item_name,size,quantity,actual,unit',
            'A4 Paper,A4,6 Rim,1 1/2,Rim',
        ]));

        app(AtkItemImportService::class)->import('atk-imports/ream.csv', $ga);

        $item = AtkItem::query()->where(['name' => 'A4 Paper', 'size' => 'A4'])->sole();
        $this->assertSame('6.00', $item->current_stock);
        $this->assertSame('1.50', $item->actual_stock);
        $this->assertSame('Ream', $item->unitMaster->name);
    }

    public function test_atk_import_uses_the_unit_written_after_a_quantity(): void
    {
        $ga = User::factory()->create(['is_admin' => true]);
        Storage::fake('local');
        Storage::disk('local')->put('atk-imports/quantity-unit.csv', implode("\n", [
            'item_name,size,quantity,actual,unit',
            'Amplop KPM,Kecil,1 Box,1,Ea',
        ]));

        app(AtkItemImportService::class)->import('atk-imports/quantity-unit.csv', $ga);

        $item = AtkItem::query()->where(['name' => 'Amplop KPM', 'size' => 'Kecil'])->sole();
        $this->assertSame('1.00', $item->current_stock);
        $this->assertSame('Box', $item->unitMaster->name);
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
