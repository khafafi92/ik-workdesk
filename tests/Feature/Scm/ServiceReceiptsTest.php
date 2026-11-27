<?php

namespace Tests\Feature\Scm;

use App\Filament\Pages\FinancePayments;
use App\Filament\Pages\ServiceRequests;
use App\Livewire\ScmServiceReceipts;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Scm\Cbe;
use App\Models\Scm\Procurement;
use App\Models\Scm\PurchaseOrder;
use App\Models\Scm\Vendor;
use App\Models\User;
use App\Services\Finance\PoPayments;
use App\Services\Scm\PurchaseOrders;
use App\Services\Scm\ServiceReceipts;
use Database\Seeders\ScmSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ServiceReceiptsTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private User $processor;

    private User $finance;

    private PurchaseOrder $order;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('scm');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->seed(ScmSeeder::class);
        $department = Department::create(['code' => 'SR-QA', 'name' => 'Departemen SR']);
        $this->requester = $this->user($department, 'scm.request');
        $this->processor = $this->user($department, 'scm.process');
        $this->finance = $this->user($department, 'finance.payments');

        $vendor = Vendor::create(['code' => 'SR-QA', 'name' => 'Vendor Servis QA', 'provider_type' => 'jasa',
            'created_by' => $this->processor->id, 'updated_by' => $this->processor->id]);
        $request = Procurement::create(['company' => 'KPMOG', 'number' => 'SR-QA', 'kind' => 'SR', 'need_type' => 'HO',
            'date' => today(), 'requester_id' => $this->requester->id, 'department_id' => $department->id,
            'title' => 'Servis AC kantor', 'reason' => 'Pemeliharaan berkala', 'status' => 'completed']);
        $work = $request->items()->create(['name' => 'Servis AC', 'specification' => 'Pembersihan dan uji fungsi',
            'quantity_milli' => 5000, 'unit' => 'unit', 'position' => 1]);
        $quote = ['id' => 1, 'version' => 1, 'number' => 'QT-SR', 'currency' => 'IDR', 'extra_minor' => 0,
            'total_minor' => 50000, 'lead_time' => '7 hari', 'payment_terms' => 'Setelah hasil diterima', 'vendor' => $vendor->toArray(),
            'lines' => [['request_item_id' => $work->id, 'quantity_milli' => 5000, 'unit' => 'unit', 'price_minor' => 10000]],
            'evaluation' => ['subtotal_minor' => 50000, 'discount_minor' => 0, 'net_minor' => 50000,
                'tax_minor' => 0, 'total_minor' => 50000, 'non_pkp' => true]];
        $cbe = Cbe::create(['request_id' => $request->id, 'number' => 'CBE-SR-QA', 'date' => today(), 'status' => 'final',
            'prepared_by' => $this->processor->id, 'selected_vendor_id' => $vendor->id, 'final_minor' => 50000,
            'snapshot' => ['approval_required' => true, 'items' => [$work->toArray()], 'quotes' => [$quote]]]);
        foreach (['prepared', 'acknowledged', 'crosschecked', 'approved'] as $position => $role) {
            $cbe->approvals()->create(['position' => $position, 'role' => $role, 'user_id' => $this->processor->id,
                'assigned_name' => $this->processor->name, 'status' => 'signed']);
        }
        $request->legalCase()->create(['cbe_id' => $cbe->id, 'vendor_id' => $vendor->id,
            'scm_pic_id' => $this->processor->id, 'requested_at' => now(), 'completed_at' => now()]);
        $this->order = app(PurchaseOrders::class)->save($this->processor, $request->id, $request->fresh()->version, $this->orderForm())->purchaseOrder;
    }

    private function user(Department $department, string $permission): User
    {
        $user = User::factory()->create(['is_admin' => false]);
        Employee::create(['user_id' => $user->id, 'department_id' => $department->id, 'name' => $user->name, 'is_active' => true]);
        $user->directPermissions()->attach(Permission::where('code', $permission)->sole()->id);

        return $user->fresh();
    }

    private function orderForm(): array
    {
        return ['number' => 'PO-SR-QA', 'issued_at' => today()->toDateString(), 'delivery_terms' => 'Kantor HO',
            'delivery_time' => '7 hari', 'payment_terms' => 'Setelah hasil diterima'];
    }

    private function input(string $quantity, string $reference = 'BAST-01'): array
    {
        return ['token' => (string) Str::uuid(), 'received_on' => today()->toDateString(),
            'reference' => $reference, 'quantities' => [1 => $quantity]];
    }

    public function test_requester_records_partial_receipts_from_sr_without_changing_inventory(): void
    {
        Livewire::actingAs($this->requester)->test(ServiceRequests::class)
            ->call('open', $this->order->request_id)->assertSee('Penerimaan Jasa')->assertSee('Belum ada jasa yang diterima.');

        $component = Livewire::actingAs($this->requester)->test(ScmServiceReceipts::class, ['orderId' => $this->order->id]);
        $component->set('receipt.reference', 'BAST-01')->set('receipt.quantities.1', '3')
            ->call('save')->assertHasNoErrors()->assertSee('Sebagian jasa sudah diterima.')->assertSee('BAST-01');
        $component->set('receipt.reference', 'BAST-02')->set('receipt.quantities.1', '3')->call('save')->assertHasErrors('quantities');
        $component->set('receipt.quantities.1', '2')->call('save')->assertHasNoErrors()
            ->assertSee('Seluruh jasa pada PO sudah diterima.')->assertSee('BAST-01')->assertSee('BAST-02')
            ->assertDontSee('Konfirmasi penerimaan jasa');

        $this->assertDatabaseHas('scm_service_receipts', ['accepted_by' => $this->requester->id, 'reference' => 'BAST-01']);
        $this->assertDatabaseHas('scm_audits', ['action' => 'penerimaan_jasa', 'request_id' => $this->order->request_id]);
        $this->assertDatabaseCount('inventory_receipts', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_finance_can_review_private_evidence_and_the_received_order_is_locked(): void
    {
        $file = UploadedFile::fake()->createWithContent('BAST.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n");
        $saved = app(ServiceReceipts::class)->receive($this->requester, $this->order->id, $this->input('3'), $file);
        Storage::disk('scm')->assertExists($saved->evidence_path);
        $url = route('scm.service-receipt-download', $saved);
        $this->actingAs($this->finance)->get($url)->assertOk()->assertDownload('BAST.pdf');
        $outsiderDepartment = Department::create(['code' => 'OTHER-SR', 'name' => 'Departemen lain']);
        $this->actingAs($this->user($outsiderDepartment, 'finance.payments'))->get($url)->assertNotFound();

        Livewire::actingAs($this->finance)->test(FinancePayments::class)->call('openOrder', $this->order->id)
            ->assertSee('BAST-01')->assertSee('Sebagian jasa sudah diterima.')->assertDontSee('Konfirmasi penerimaan jasa');
        Livewire::actingAs($this->finance)->test(ScmServiceReceipts::class, ['orderId' => $this->order->id, 'readOnly' => true])
            ->call('save')->assertForbidden();

        try {
            app(PurchaseOrders::class)->save($this->processor, $this->order->request_id, $this->order->request->version, $this->orderForm());
            $this->fail('PO jasa dengan penerimaan tidak boleh diubah.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('penerimaan jasa', $error->getMessage());
        }
        $this->expectException(ValidationException::class);
        app(PoPayments::class)->cancelOrder($this->finance, $this->order->id, 'Alasan pembatalan QA');
    }

    public function test_receipt_retries_are_idempotent_and_invalid_lines_do_not_commit(): void
    {
        $service = app(ServiceReceipts::class);
        $input = $this->input('3,125');
        $receipt = $service->receive($this->requester, $this->order->id, $input);
        $this->assertSame($receipt->id, $service->receive($this->requester, $this->order->id, $input)->id);

        foreach ([[...$input, 'reference' => 'Berubah'], [...$this->input('1'), 'quantities' => [1 => '1', 2 => '1']]] as $invalid) {
            try {
                $service->receive($this->requester, $this->order->id, $invalid);
                $this->fail('Baris PO atau penerimaan ulangan tidak valid.');
            } catch (ValidationException $error) {
                $this->assertNotEmpty($error->errors());
            }
        }
        $this->assertDatabaseCount('scm_service_receipts', 1);
        $this->assertDatabaseHas('scm_service_receipt_lines', ['quantity_milli' => 3125]);
        $this->assertDatabaseCount('scm_service_receipt_lines', 1);
    }

    public function test_only_request_author_can_receive_services_on_an_active_sr_order(): void
    {
        $service = app(ServiceReceipts::class);
        foreach ([$this->processor, $this->finance] as $actor) {
            try {
                $service->receive($actor, $this->order->id, $this->input('1'));
                $this->fail('Penerimaan memerlukan akses pemohon.');
            } catch (HttpException $error) {
                $this->assertSame(403, $error->getStatusCode());
            }
        }
        $this->order->update(['status' => 'cancelled']);
        $this->expectException(HttpException::class);
        $service->receive($this->requester, $this->order->id, $this->input('1'));
    }
}
