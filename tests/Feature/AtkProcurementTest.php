<?php

namespace Tests\Feature;

use App\Filament\Resources\AtkRequests\Pages\CreateAtkRequest;
use App\Filament\Resources\AtkRequests\Pages\ListAtkRequests;
use App\Models\AtkItem;
use App\Models\AtkRequest;
use App\Models\AtkRequestItem;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\PermitCompany;
use App\Models\Scm\Procurement;
use App\Models\Scm\ProjectNumbering;
use App\Models\User;
use App\Services\AtkDepartmentStockService;
use App\Services\AtkProcurementService;
use App\Services\AtkWarehouseStockService;
use App\Services\Inventory\InventoryStock;
use App\Services\Inventory\Warehouses;
use App\Services\Scm\Workflow;
use Database\Seeders\ScmSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AtkProcurementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private AtkRequest $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->seed(ScmSeeder::class);
        $this->manager = User::factory()->create(['is_admin' => true]);
        $requester = User::factory()->create();
        $department = Department::create(['code' => 'ATK-PR', 'name' => 'Peminta ATK', 'is_active' => true]);
        $this->source = AtkRequest::create(['request_number' => 'ATK/TEST/2026/0001', 'request_date' => today(),
            'requester_id' => $requester->id, 'department_id' => $department->id,
            'permit_company_id' => PermitCompany::where('code', 'KPMOG')->sole()->id,
            'purpose' => 'Keperluan operasional', 'status' => 'submitted', 'created_by' => $requester->id]);
        $this->actingAs($this->manager);
    }

    private function line(string $code, float $quantity, float $stock): AtkRequestItem
    {
        $item = AtkItem::create(['code' => $code, 'name' => $code, 'unit' => 'PCS', 'current_stock' => 0, 'is_active' => true]);
        if ($stock > 0) {
            $source = $this->source->fresh();
            $warehouse = app(Warehouses::class)->forContext($source->company->code, $source->need_type, $source->project_numbering_id);
            app(AtkWarehouseStockService::class)->incoming($item, $stock, $this->manager, 'Saldo awal gudang entitas pengujian.', $warehouse);
        }

        return $this->source->items()->create(['atk_item_id' => $item->id, 'qty_requested' => $quantity, 'unit' => 'PCS']);
    }

    public function test_only_shortages_become_an_idempotent_pr_draft_without_changing_stock(): void
    {
        $ready = $this->line('READY', 3, 5);
        $partial = $this->line('PARTIAL', 10, 4);
        $empty = $this->line('EMPTY', 2.5, 0);
        $service = app(AtkProcurementService::class);
        $draft = $service->createDraft($this->source, $this->manager);
        $this->assertSame('draft', $draft->status);
        $this->assertSame('PR', $draft->kind);
        $this->assertSame('KPMOG', $draft->company);
        $this->assertSame('HO', $draft->need_type);
        $this->assertSame($this->manager->id, $draft->requester_id);
        $this->assertSame([6000, 2500], $draft->items->pluck('quantity_milli')->all());
        $this->assertSame([$partial->id => 6, $empty->id => 2.5], $draft->source_atk_quantities);
        $this->assertSame('pending', $ready->fresh()->status);
        $this->assertSame('waiting_procurement', $partial->fresh()->status);
        $this->assertSame('4.00', $partial->item->fresh()->current_stock);
        $this->assertSame($draft->id, $service->createDraft($this->source, $this->manager)->id);
        $this->assertSame(1, Procurement::count());
        $this->assertSame(1, $this->source->histories()->where('action', 'pr_draft_created')->count());
        $this->assertNull($draft->prepared_signed_at);
        $this->expectException(ValidationException::class);
        app(Workflow::class)->submit($this->manager, $draft->id, $draft->version);
    }

    public function test_partial_issue_and_receipt_still_leave_only_the_shortage_for_pr(): void
    {
        $line = $this->line('PARTIAL-ISSUE', 10, 4);
        app(AtkWarehouseStockService::class)->issue($line, 4, $this->manager);
        $draft = app(AtkProcurementService::class)->createDraft($this->source, $this->manager);
        $this->assertSame(6000, $draft->items->sole()->quantity_milli);
        $this->assertSame('4.00', $line->fresh()->qty_issued);
        $this->assertSame('0.00', $line->item->fresh()->current_stock);
        $this->assertSame('partially_fulfilled', $this->source->fresh()->status);
        app(AtkWarehouseStockService::class)->incoming($line->item, 6, $this->manager, 'Penerimaan barang hasil PR', app(InventoryStock::class)->atkWarehouse($this->source));
        app(AtkWarehouseStockService::class)->issue($line->fresh(), 6, $this->manager);
        app(AtkDepartmentStockService::class)->receive($line->fresh(), $this->source->requester);
        $this->assertSame('10.00', $line->fresh()->qty_received);
        $this->assertSame('completed', $this->source->fresh()->status);
        $this->assertSame('0.00', $line->item->fresh()->current_stock);
        $this->assertSame($draft->id, $this->source->procurement->id);
    }

    public function test_project_and_company_context_is_carried_into_pr(): void
    {
        $master = ProjectNumbering::where('is_active', true)->firstOrFail();
        $this->source->update(['need_type' => 'PROJECT', 'project_numbering_id' => $master->id,
            'permit_company_id' => PermitCompany::where('code', 'APCA')->sole()->id]);
        $this->line('PROJECT', 1, 0);
        $draft = app(AtkProcurementService::class)->createDraft($this->source, $this->manager);
        $this->assertSame('APCA', $draft->company);
        $this->assertSame('PROJECT', $draft->need_type);
        $this->assertSame($master->id, $draft->project_numbering_id);
        $this->assertStringContainsString('/'.$master->code.'/PR/', $draft->number);
    }

    public function test_ga_with_pr_permission_uses_own_department_without_impersonating_requester(): void
    {
        $ga = User::factory()->create(['is_admin' => false]);
        $department = Department::create(['code' => 'GA-PR', 'name' => 'General Affairs', 'is_active' => true]);
        Employee::create(['user_id' => $ga->id, 'department_id' => $department->id, 'name' => $ga->name, 'is_active' => true]);
        $ga->directPermissions()->attach(Permission::whereIn('code', ['atk.manage', 'scm.request'])->pluck('id'));
        $this->line('GA-ITEM', 2, 0);
        $draft = app(AtkProcurementService::class)->createDraft($this->source, $ga);
        $this->assertSame($ga->id, $draft->requester_id);
        $this->assertSame($department->id, $draft->department_id);
        $this->assertSame($this->source->id, $draft->source_atk_request_id);
        $this->assertStringContainsString($this->source->requester->name, $draft->notes);
    }

    public function test_existing_inventory_cannot_be_converted_to_unnecessary_procurement(): void
    {
        $this->line('SUFFICIENT', 3, 5);
        try {
            app(AtkProcurementService::class)->createDraft($this->source, $this->manager);
            $this->fail('Stok cukup harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('procurement', $exception->errors());
        }
        $this->assertSame(0, Procurement::count());
        $this->assertDatabaseCount('scm_request_numbers', 0);
    }

    public function test_cancelled_requests_and_unit_mismatches_cannot_be_converted(): void
    {
        $line = $this->line('INVALID', 3, 0);
        $line->update(['unit' => 'BOX']);
        foreach (['submitted', 'cancelled', 'completed'] as $status) {
            $this->source->update(['status' => $status]);
            try {
                app(AtkProcurementService::class)->createDraft($this->source, $this->manager);
                $this->fail('Permintaan tidak valid harus ditolak.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('procurement', $exception->errors());
            }
        }
        $this->assertSame(0, Procurement::count());
    }

    public function test_conversion_requires_both_atk_management_and_pr_permission(): void
    {
        $this->line('PRIVATE', 3, 0);
        foreach ([[], ['atk.manage'], ['scm.request']] as $permissions) {
            $user = User::factory()->create(['is_admin' => false]);
            $user->directPermissions()->attach(Permission::whereIn('code', $permissions)->pluck('id'));
            try {
                app(AtkProcurementService::class)->createDraft($this->source, $user);
                $this->fail('Pengguna tanpa kedua izin harus ditolak.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $this->assertSame(0, Procurement::count());
    }

    public function test_pr_button_creates_a_linked_draft_and_disappears_after_conversion(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->line('BUTTON-ITEM', 3, 1);
        Livewire::test(ListAtkRequests::class)->assertTableActionVisible('create_pr', $this->source)
            ->callTableAction('create_pr', $this->source)->assertHasNoErrors()
            ->assertTableActionHidden('create_pr', $this->source);
        $this->assertSame(2000, $this->source->procurement->items->sole()->quantity_milli);
    }

    public function test_project_atk_form_requires_an_active_project_code(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(CreateAtkRequest::class)->fillForm(['purpose' => 'ATK Project', 'need_type' => 'PROJECT',
            'permit_company_id' => $this->source->permit_company_id, 'requester_department_id' => $this->source->department_id,
            'items' => [['atk_item_id' => $this->line('FORM-ITEM', 1, 0)->atk_item_id, 'qty_requested' => 1, 'unit' => 'PCS']]])
            ->call('create')->assertHasFormErrors(['project_numbering_id' => 'required']);
    }
}
