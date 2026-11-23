<?php

namespace Tests\Feature;

use App\Filament\Pages\FinancePayments;
use App\Models\Department;
use App\Models\Employee;
use App\Models\InventoryReceipt;
use App\Models\InventoryWarehouse;
use App\Models\FinanceAccount;
use App\Models\FinanceBudgetLine;
use App\Models\Permission;
use App\Models\Scm\Cbe;
use App\Models\Scm\Procurement;
use App\Models\Scm\PurchaseOrder;
use App\Models\Scm\Vendor;
use App\Models\User;
use App\Services\Finance\PoPayments;
use App\Services\Finance\BudgetLedger;
use App\Services\Finance\BudgetMasters;
use Database\Seeders\ScmSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class FinancePaymentsTest extends TestCase
{
    use RefreshDatabase;

    private User $finance;

    private User $outsider;

    private PurchaseOrder $order;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('scm');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->seed(ScmSeeder::class);
        $department = Department::create(['code' => 'FIN-QA', 'name' => 'Departemen QA']);
        $otherDepartment = Department::create(['code' => 'OTHER-QA', 'name' => 'Departemen lain']);
        $this->finance = $this->user($department, true);
        $this->outsider = $this->user($otherDepartment, true);
        $vendor = Vendor::create(['code' => 'FIN-QA', 'name' => 'Vendor QA', 'provider_type' => 'barang',
            'created_by' => $this->finance->id, 'updated_by' => $this->finance->id]);
        $request = Procurement::create(['company' => 'KPMOG', 'number' => 'PR-FIN-QA', 'kind' => 'PR', 'need_type' => 'PROJECT',
            'date' => today(), 'requester_id' => $this->finance->id, 'department_id' => $department->id,
            'title' => 'Pengadaan QA', 'reason' => 'QA', 'status' => 'completed']);
        $cbe = Cbe::create(['request_id' => $request->id, 'number' => 'CBE-FIN-QA', 'date' => today(), 'status' => 'final',
            'prepared_by' => $this->finance->id, 'selected_vendor_id' => $vendor->id, 'final_minor' => 1000000,
            'snapshot' => ['approval_required' => true]]);
        foreach (['prepared', 'acknowledged', 'crosschecked', 'approved'] as $position => $role) {
            $cbe->approvals()->create(['position' => $position, 'role' => $role, 'user_id' => $this->finance->id,
                'assigned_name' => 'Pengujian', 'status' => 'signed']);
        }
        $request->legalCase()->create(['cbe_id' => $cbe->id, 'vendor_id' => $vendor->id, 'scm_pic_id' => $this->finance->id,
            'requested_at' => now(), 'completed_at' => now()]);
        $this->order = PurchaseOrder::create(['request_id' => $request->id, 'cbe_id' => $cbe->id, 'company' => 'KPMOG',
            'number' => 'PO-FIN-QA', 'number_key' => 'po-fin-qa', 'issued_at' => today(), 'delivery_terms' => 'Gudang',
            'delivery_time' => '7 hari', 'payment_terms' => 'DP 5% dan pelunasan', 'snapshot' => ['currency' => 'IDR', 'company' => 'KPMOG',
                'request_number' => $request->number, 'quotation_number' => 'QT-QA', 'vendor' => ['name' => $vendor->name],
                'lines' => [['name' => 'Barang QA', 'specification' => 'QA', 'quantity_milli' => 1000, 'unit' => 'pcs', 'price_minor' => 1000000, 'gross_minor' => 1000000]],
                'extra_minor' => 0, 'evaluation' => ['subtotal_minor' => 1000000, 'discount_minor' => 0, 'net_minor' => 1000000, 'tax_minor' => 0, 'total_minor' => 1000000, 'non_pkp' => true]],
            'created_by' => $this->finance->id, 'updated_by' => $this->finance->id]);
    }

    private function user(Department $department, bool $mayPay): User
    {
        $user = User::factory()->create(['is_admin' => false]);
        Employee::create(['user_id' => $user->id, 'department_id' => $department->id, 'name' => $user->name, 'is_active' => true]);
        if ($mayPay) {
            $user->directPermissions()->attach(Permission::where('code', 'finance.payments')->sole()->id);
        }

        return $user->fresh();
    }

    private function input(string $amount = '500'): array
    {
        return ['token' => (string) Str::uuid(), 'kind' => 'dp', 'description' => 'DP 5%', 'timing' => 'before_delivery', 'due_on' => today()->toDateString(), 'amount' => $amount];
    }

    private function pdf(string $name = 'file.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n");
    }

    public function test_finance_can_record_multiple_terms_and_private_evidence_without_blocking_receipts(): void
    {
        $service = app(PoPayments::class);
        $dp = $service->plan($this->finance, $this->order->id, $this->input(), $this->pdf('invoice.pdf'));
        $this->assertSame(50000, $dp->amount_minor);
        $this->assertSame('planned', $dp->status);
        $service->recordPaid($this->finance, $dp->id, ['paid_on' => today()->toDateString(), 'payment_reference' => 'TRX-01'], $this->pdf('proof.pdf'), null);
        $this->assertSame('paid', $dp->fresh()->status);
        $this->assertCount(2, $dp->files);
        $this->assertSame(0, InventoryReceipt::where('purchase_order_id', $this->order->id)->count());
        $settlement = $service->plan($this->finance, $this->order->id, [...$this->input('9500'), 'kind' => 'settlement', 'timing' => 'after_receipt'], null);
        $this->assertSame('planned', $settlement->status);
        $file = $dp->files()->where('type', 'proof')->sole();
        $this->actingAs($this->finance)->get(route('finance.payment-file', $file))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($this->outsider)->get(route('finance.payment-file', $file))->assertNotFound();
        $this->assertDatabaseHas('finance_po_payment_events', ['payment_id' => $dp->id, 'action' => 'catat_pembayaran_po']);
        $this->assertDatabaseMissing('scm_audits', ['request_id' => $this->order->request_id, 'action' => 'catat_pembayaran_po']);
    }

    public function test_amounts_scope_and_paid_immutability_are_enforced(): void
    {
        $service = app(PoPayments::class);
        try {
            $service->plan($this->outsider, $this->order->id, $this->input(), null);
            $this->fail('Akses departemen lain harus ditolak.');
        } catch (ModelNotFoundException $error) {
            $this->assertTrue(true);
        }
        $payment = $service->plan($this->finance, $this->order->id, $this->input('10000'), null);
        try {
            $service->plan($this->finance, $this->order->id, $this->input('1'), null);
            $this->fail('Termin melebihi PO harus ditolak.');
        } catch (ValidationException $error) {
            $this->assertNotEmpty($error->errors());
        }
        $service->recordPaid($this->finance, $payment->id, ['paid_on' => today()->toDateString(), 'payment_reference' => 'TRX-01'], $this->pdf(), null);
        try {
            $service->recordPaid($this->finance, $payment->id, ['paid_on' => today()->toDateString(), 'payment_reference' => 'TRX-02'], $this->pdf(), null);
            $this->fail('Pembayaran tidak boleh dicatat dua kali.');
        } catch (ValidationException $error) {
            $this->assertNotEmpty($error->errors());
        }
        $this->assertSame(1, $payment->files()->where('type', 'proof')->count());
    }

    public function test_repeated_plan_request_does_not_create_a_duplicate(): void
    {
        $service = app(PoPayments::class);
        $input = $this->input();
        $first = $service->plan($this->finance, $this->order->id, $input, null);
        $again = $service->plan($this->finance, $this->order->id, $input, null);
        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, $this->order->payments()->count());
        try {
            $service->plan($this->finance, $this->order->id, [...$input, 'amount' => '600'], null);
            $this->fail('Token yang sama tidak boleh dipakai dengan nominal berbeda.');
        } catch (ValidationException $error) {
            $this->assertNotEmpty($error->errors());
        }
    }

    public function test_repeated_plan_with_a_different_invoice_is_rejected(): void
    {
        $service = app(PoPayments::class);
        $input = $this->input();
        $first = $service->plan($this->finance, $this->order->id, $input, $this->pdf('invoice.pdf'));
        $again = $service->plan($this->finance, $this->order->id, $input, $this->pdf('invoice.pdf'));
        $this->assertSame($first->id, $again->id);
        $changed = UploadedFile::fake()->createWithContent('invoice.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n% Changed invoice\n%%EOF\n");
        try {
            $service->plan($this->finance, $this->order->id, $input, $changed);
            $this->fail('Invoice berbeda tidak boleh dianggap permintaan yang sama.');
        } catch (ValidationException $error) {
            $this->assertNotEmpty($error->errors());
        }
        $this->assertSame(1, $this->order->payments()->count());
        $this->assertSame(1, $first->files()->count());
    }

    public function test_cancelled_plan_can_be_replaced_and_unverified_files_are_rejected(): void
    {
        $service = app(PoPayments::class);
        $payment = $service->plan($this->finance, $this->order->id, $this->input('10000'), null);
        $service->cancel($this->finance, $payment->id, 'Perubahan termin vendor');
        $this->assertSame('cancelled', $payment->fresh()->status);
        $this->assertSame('planned', $service->plan($this->finance, $this->order->id, $this->input('10000'), null)->status);
        $bad = UploadedFile::fake()->createWithContent('bad.pdf', 'not a pdf');
        try {
            $service->plan($this->finance, $this->order->id, $this->input('1'), $bad);
            $this->fail('File tidak valid harus ditolak.');
        } catch (ValidationException $error) {
            $this->assertNotEmpty($error->errors());
        }
    }

    public function test_finance_page_can_plan_and_record_payment_with_upload(): void
    {
        Livewire::actingAs($this->finance)->test(FinancePayments::class)->assertSee('PO-FIN-QA')->call('openOrder', $this->order->id)
            ->assertSee('DP 5% dan pelunasan')->set('plan', $this->input())->set('invoiceFile', $this->pdf('invoice.pdf'))
            ->call('savePlan')->assertHasNoErrors()->assertSee('Unpaid');
        $payment = $this->order->payments()->sole();
        Livewire::actingAs($this->finance)->test(FinancePayments::class)->call('openOrder', $this->order->id)
            ->call('preparePaid', $payment->id)->set('paid.payment_reference', 'TRX-QA')->set('proofFile', $this->pdf('proof.pdf'))
            ->call('savePaid')->assertHasNoErrors()->assertSee('Paid')->assertSee('Bukti bayar');
        $this->assertSame('paid', $payment->fresh()->status);
    }

    public function test_finance_sees_current_selected_vendor_bank_details_and_missing_state(): void
    {
        $vendor = Vendor::findOrFail($this->order->cbe->selected_vendor_id);
        $page = Livewire::actingAs($this->finance)->test(FinancePayments::class)
            ->call('openOrder', $this->order->id)->assertSee('Vendor account details are incomplete. Complete vendor profile before being used for payment.');

        $vendor->update(['bank_name' => 'Bank QA', 'bank_account_number' => '0012345678', 'bank_account_name' => 'PT Vendor QA']);
        $page->call('$refresh')->assertSee('Bank QA')->assertSee('0012345678')->assertSee('PT Vendor QA')
            ->assertDontSee('Vendor account details are incomplete. Complete vendor profile before being used for payment.');

        $other = Vendor::create(['code' => 'OTHER-BANK', 'name' => 'Vendor lain', 'provider_type' => 'barang',
            'bank_account_number' => '99999999', 'created_by' => $this->finance->id, 'updated_by' => $this->finance->id]);
        $page->call('$refresh')->assertDontSee($other->bank_account_number);
    }

    public function test_paid_term_preserves_the_bank_details_at_payment_time(): void
    {
        $vendor = $this->order->cbe->selectedVendor;
        $bank = ['bank_name' => 'Bank QA', 'bank_account_number' => '0012345678', 'bank_account_name' => 'PT Vendor QA'];
        $vendor->update($bank);
        $service = app(PoPayments::class);
        $payment = $service->plan($this->finance, $this->order->id, $this->input(), null);
        $service->recordPaid($this->finance, $payment->id, ['paid_on' => today()->toDateString(), 'payment_reference' => 'TRX'], $this->pdf(), null, $bank);
        $vendor->update(['bank_account_number' => '9876543210']);

        $this->assertSame($bank, $payment->fresh()->vendor_bank_snapshot);
        Livewire::actingAs($this->finance)->test(FinancePayments::class)
            ->call('openOrder', $this->order->id)->assertSee('0012345678')->assertSee('9876543210');
    }

    public function test_payment_rejects_bank_details_that_changed_after_confirmation(): void
    {
        $vendor = $this->order->cbe->selectedVendor;
        $oldBank = ['bank_name' => 'Bank QA', 'bank_account_number' => '0012345678', 'bank_account_name' => 'PT Vendor QA'];
        $vendor->update($oldBank);
        $service = app(PoPayments::class);
        $payment = $service->plan($this->finance, $this->order->id, $this->input(), null);
        $vendor->update(['bank_account_number' => '9876543210']);

        try {
            $service->recordPaid($this->finance, $payment->id, ['paid_on' => today()->toDateString(), 'payment_reference' => 'TRX'], $this->pdf(), null, $oldBank);
            $this->fail('Perubahan rekening harus diperiksa sebelum mencatat pembayaran.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
        $this->assertSame('planned', $payment->fresh()->status);
        $this->assertCount(0, $payment->files);
    }

    public function test_payment_page_rejects_a_bank_change_after_the_form_is_opened(): void
    {
        $vendor = $this->order->cbe->selectedVendor;
        $vendor->update(['bank_name' => 'Bank QA', 'bank_account_number' => '0012345678', 'bank_account_name' => 'PT Vendor QA']);
        $payment = app(PoPayments::class)->plan($this->finance, $this->order->id, $this->input(), null);
        $page = Livewire::actingAs($this->finance)->test(FinancePayments::class)
            ->call('openOrder', $this->order->id)->call('preparePaid', $payment->id);
        $vendor->update(['bank_account_number' => '9876543210']);

        $page->set('paid.payment_reference', 'TRX')->set('proofFile', $this->pdf())
            ->call('savePaid')->assertHasErrors('finance');
        $this->assertSame('planned', $payment->fresh()->status);
    }

    public function test_existing_payment_does_not_assume_the_current_bank_was_used(): void
    {
        $vendor = $this->order->cbe->selectedVendor;
        $vendor->update(['bank_name' => 'Bank QA', 'bank_account_number' => '0012345678', 'bank_account_name' => 'PT Vendor QA']);
        $payment = app(PoPayments::class)->plan($this->finance, $this->order->id, $this->input(), null);
        $payment->update(['status' => 'paid', 'paid_on' => today(), 'vendor_bank_snapshot' => null]);

        Livewire::actingAs($this->finance)->test(FinancePayments::class)
            ->call('openOrder', $this->order->id)->assertSee('Rekening saat dicatat: Tidak tercatat');
    }

    public function test_order_list_shows_actual_receipts_plans_and_accessible_orders(): void
    {
        app(PoPayments::class)->plan($this->finance, $this->order->id, $this->input(), null);
        Livewire::actingAs($this->finance)->test(FinancePayments::class)
            ->assertSee('No receipts have been recorded for this PO.')->assertSee('1 termin tercatat')->assertSee('Unpaid installments');
        Livewire::actingAs($this->outsider)->test(FinancePayments::class)->assertDontSee('PO-FIN-QA');
    }

    public function test_finance_cancels_an_unpaid_po_and_releases_its_budget_commitment(): void
    {
        $line = $this->budgetLineForOrder();
        $payment = app(PoPayments::class)->plan($this->finance, $this->order->id, $this->input(), null);
        $this->assertSame(1000000, app(BudgetLedger::class)->lineSummary($line)['used_minor']);

        $cancelled = app(PoPayments::class)->cancelOrder($this->finance, $this->order->id, 'Pengadaan dibatalkan oleh Finance');

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('cancelled', $payment->fresh()->status);
        $this->assertSame(0, app(BudgetLedger::class)->lineSummary($line)['used_minor']);
        $this->assertSame(2000000, app(BudgetLedger::class)->lineSummary($line)['available_minor']);
        $this->assertDatabaseHas('scm_audits', ['request_id' => $this->order->request_id, 'action' => 'batalkan_po']);
        $this->assertDatabaseHas('finance_po_payment_events', ['payment_id' => $payment->id, 'action' => 'batal_termin_po']);
    }

    public function test_finance_cannot_cancel_a_po_after_payment(): void
    {
        $service = app(PoPayments::class);
        $payment = $service->plan($this->finance, $this->order->id, $this->input(), null);
        $service->recordPaid($this->finance, $payment->id,
            ['paid_on' => today()->toDateString(), 'payment_reference' => 'TRX-QA'], $this->pdf('proof.pdf'), null);
        try {
            $service->cancelOrder($this->finance, $this->order->id, 'Uji pembatalan setelah pembayaran');
            $this->fail('PO dengan pembayaran tidak dapat dibatalkan.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('paid PO', $error->getMessage());
        }
    }

    public function test_finance_cannot_cancel_a_po_after_receipt(): void
    {
        $service = app(PoPayments::class);
        $warehouse = InventoryWarehouse::create(['code' => 'FIN-CANCEL-QA', 'name' => 'Gudang QA', 'kind' => 'PROJECT']);
        InventoryReceipt::create(['token' => (string) Str::uuid(), 'request_id' => $this->order->request_id, 'purchase_order_id' => $this->order->id,
            'warehouse_id' => $warehouse->id, 'received_on' => today(), 'reference' => 'GR-CANCEL-QA', 'created_by' => $this->finance->id]);
        try {
            $service->cancelOrder($this->finance, $this->order->id, 'Uji pembatalan setelah penerimaan');
            $this->fail('PO dengan penerimaan tidak dapat dibatalkan.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('receipt', $error->getMessage());
        }
        $this->assertSame('active', $this->order->fresh()->status);
    }

    public function test_finance_page_exposes_the_audited_po_cancellation_flow(): void
    {
        Livewire::actingAs($this->finance)->test(FinancePayments::class)->call('openOrder', $this->order->id)
            ->assertSee('Cancel entire PO')->call('prepareCancelOrder')
            ->set('orderCancelReason', 'Pengadaan dibatalkan oleh Finance')
            ->call('cancelOrder')->assertHasNoErrors()->assertSee('PO dibatalkan pada')
            ->assertDontSee('Add installment');
    }

    public function test_percentage_suggests_editable_amount_from_the_po_total(): void
    {
        $page = Livewire::actingAs($this->finance)->test(FinancePayments::class)
            ->call('openOrder', $this->order->id)
            ->assertSet('plan.description', 'DP 5%')
            ->assertSet('plan.amount', '500,00');

        $page->set('plan.description', 'Termin 12,5%')->assertSet('plan.amount', '1250,00')
            ->set('plan.description', 'Termin tanpa persentase')->assertSet('plan.amount', '')
            ->set('plan.description', 'DP 5%')->set('plan.amount', '450')
            ->set('plan.description', 'DP 6%')->assertSet('plan.amount', '450');
    }

    public function test_settlement_suggests_unallocated_balance_without_overwriting_it_from_description(): void
    {
        $service = app(PoPayments::class);
        $paid = $service->plan($this->finance, $this->order->id, $this->input('1000'), null);
        $service->recordPaid($this->finance, $paid->id, ['paid_on' => today()->toDateString(), 'payment_reference' => 'TRX'], $this->pdf(), null);
        $service->plan($this->finance, $this->order->id, [...$this->input('2000'), 'kind' => 'progress'], null);
        $cancelled = $service->plan($this->finance, $this->order->id, [...$this->input('500'), 'kind' => 'progress'], null);
        $service->cancel($this->finance, $cancelled->id, 'Perubahan rencana termin');

        $page = Livewire::actingAs($this->finance)->test(FinancePayments::class)->call('openOrder', $this->order->id)
            ->set('plan.kind', 'settlement')->assertSet('plan.description', 'Pelunasan')->assertSet('plan.amount', '7000,00')
            ->set('plan.description', 'Pelunasan 70%')->assertSet('plan.amount', '7000,00')
            ->set('plan.amount', '6500')->set('plan.description', 'Pelunasan sesuai invoice')->assertSet('plan.amount', '6500')
            ->set('plan.kind', 'progress')->assertSet('plan.amount', '6500');
    }

    public function test_settlement_with_no_unallocated_balance_does_not_suggest_a_payment(): void
    {
        app(PoPayments::class)->plan($this->finance, $this->order->id, $this->input('10000'), null);
        Livewire::actingAs($this->finance)->test(FinancePayments::class)->call('openOrder', $this->order->id)
            ->set('plan.kind', 'settlement')->assertSet('plan.amount', '')
            ->assertSee('All eligible POs already have payment installments.');
    }

    public function test_ambiguous_payment_terms_are_not_prefilled(): void
    {
        $this->order->update(['payment_terms' => 'DP 5% dan pelunasan 95%']);
        Livewire::actingAs($this->finance)->test(FinancePayments::class)->call('openOrder', $this->order->id)
            ->assertSet('plan.description', '')->assertSet('plan.amount', '')
            ->set('plan.description', 'DP 5% dan 95%')->assertSet('plan.amount', '');
    }

    public function test_after_receipt_payment_requires_a_receipt_for_that_po(): void
    {
        $service = app(PoPayments::class);
        $payment = $service->plan($this->finance, $this->order->id, [...$this->input(), 'timing' => 'after_receipt'], null);
        try {
            $service->recordPaid($this->finance, $payment->id, ['paid_on' => today()->toDateString(), 'payment_reference' => 'TRX'], $this->pdf(), null);
            $this->fail('Penerimaan untuk PO ini belum ada.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('receipt', $error->getMessage());
        }
        $warehouse = InventoryWarehouse::create(['code' => 'FIN-QA', 'name' => 'Gudang QA', 'kind' => 'PROJECT']);
        InventoryReceipt::create(['token' => (string) Str::uuid(), 'request_id' => $this->order->request_id, 'purchase_order_id' => $this->order->id,
            'warehouse_id' => $warehouse->id, 'received_on' => today(), 'reference' => 'GR-QA', 'created_by' => $this->finance->id]);
        $service->recordPaid($this->finance, $payment->id, ['paid_on' => today()->toDateString(), 'payment_reference' => 'TRX'], $this->pdf(), null);
        $this->assertSame('paid', $payment->fresh()->status);
        Livewire::actingAs($this->finance)->test(FinancePayments::class)->call('openOrder', $this->order->id)->assertSee('GR-QA');
    }

    public function test_invoice_can_be_added_after_payment_once(): void
    {
        $service = app(PoPayments::class);
        $payment = $service->plan($this->finance, $this->order->id, $this->input(), null);
        $service->recordPaid($this->finance, $payment->id, ['paid_on' => today()->toDateString(), 'payment_reference' => 'TRX'], $this->pdf(), null);
        Livewire::actingAs($this->finance)->test(FinancePayments::class)->call('openOrder', $this->order->id)
            ->call('prepareInvoice', $payment->id)->set('lateInvoiceNumber', 'INV-QA')->set('lateInvoiceFile', $this->pdf('invoice.pdf'))
            ->call('saveInvoice')->assertHasNoErrors()->assertSee('INV-QA');
        $this->assertSame('INV-QA', $payment->fresh()->invoice_number);
        $this->assertSame(1, $payment->files()->where('type', 'invoice')->count());
        try {
            $service->attachInvoice($this->finance, $payment->id, 'INV-DUP', $this->pdf());
            $this->fail('Invoice tidak boleh digandakan.');
        } catch (ValidationException $error) {
            $this->assertNotEmpty($error->errors());
        }
    }

    public function test_finance_can_view_only_po_in_its_accessible_department(): void
    {
        $url = route('scm.purchase-order-print', $this->order);
        $this->actingAs($this->finance)->get($url)->assertOk()->assertSeeText('PO-FIN-QA');
        $this->actingAs($this->outsider)->get($url)->assertNotFound();
        $noPermission = $this->user($this->finance->employee->department, false);
        $this->actingAs($noPermission)->get($url)->assertForbidden();
    }

    public function test_finance_explains_when_its_database_tables_are_missing(): void
    {
        Schema::drop('finance_po_payment_events');
        Schema::drop('finance_po_payment_files');
        Schema::drop('finance_po_payments');
        Livewire::actingAs($this->finance)->test(FinancePayments::class)
            ->assertSee('The payment module is not set up in the database. Administrator database needs to run the Finance migration.');
    }

    private function budgetLineForOrder(): FinanceBudgetLine
    {
        $this->finance->directPermissions()->attach(Permission::where('code', 'finance.budget.manage')->sole()->id);
        $account = FinanceAccount::create(['company' => 'KPMOG', 'code' => 'FIN-CANCEL-QA', 'name' => 'Anggaran QA',
            'is_active' => true, 'created_by' => $this->finance->id, 'updated_by' => $this->finance->id]);
        $masters = app(BudgetMasters::class);
        $budget = $masters->createBudget($this->finance, ['company' => 'KPMOG', 'year' => 2026, 'amount' => '20000']);
        $scope = $masters->createScope($this->finance, $budget->id, ['need_type' => 'HO', 'amount' => '20000']);
        $masters->createLine($this->finance, $scope->id, ['department_id' => $this->finance->employee->department_id,
            'account_id' => $account->id, 'amount' => '20000']);
        $this->order->request->update(['need_type' => 'HO', 'finance_budget_line_id' => $scope->lines()->sole()->id]);

        return $scope->lines()->sole();
    }
}
