<?php

namespace Tests\Feature\Scm;

use App\Filament\Pages\ScmProcurement;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Scm\Cbe;
use App\Models\Scm\Procurement;
use App\Models\Scm\Vendor;
use App\Models\User;
use App\Services\Scm\AmountInWords;
use App\Services\Scm\PoApprovals;
use App\Services\Scm\PurchaseOrders;
use Database\Seeders\ScmSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $scm;

    private User $outsider;

    private Procurement $request;

    private Cbe $cbe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->seed(ScmSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $department = Department::create(['code' => 'PO-TEST', 'name' => 'Departemen PO', 'is_active' => true]);
        $this->scm = User::factory()->create(['is_admin' => false]);
        Employee::create(['user_id' => $this->scm->id, 'department_id' => $department->id, 'name' => 'Petugas PO', 'is_active' => true]);
        $this->scm->directPermissions()->attach(Permission::where('code', 'scm.process')->sole()->id);
        $this->scm = $this->scm->fresh();
        $this->outsider = User::factory()->create(['is_admin' => false]);
        $vendor = Vendor::create(['code' => 'PO-VENDOR', 'name' => 'Vendor Pengujian', 'provider_type' => 'barang',
            'address' => 'Alamat awal', 'pic_name' => 'Kontak vendor', 'created_by' => $this->scm->id, 'updated_by' => $this->scm->id]);
        $this->request = Procurement::create(['company' => 'KPMOG', 'number' => 'PR-PO-TEST', 'kind' => 'PR', 'need_type' => 'PROJECT',
            'date' => today(), 'requester_id' => $this->scm->id, 'department_id' => $department->id,
            'title' => 'Pengadaan pengujian', 'reason' => 'Uji PO', 'status' => 'selected'])->fresh();
        $quote = ['id' => 123, 'version' => 1, 'number' => 'QT-123', 'total_minor' => 21500, 'currency' => 'IDR', 'lead_time' => '2 minggu',
            'payment_terms' => '30 hari', 'extra_minor' => 2500, 'vendor' => $vendor->toArray(),
            'lines' => [['request_item_id' => 45, 'quantity_milli' => 2000, 'unit' => 'pcs', 'price_minor' => 10000, 'discount_minor' => 1000]],
            'evaluation' => ['subtotal_minor' => 20000, 'discount_minor' => 1500, 'net_minor' => 21000,
                'tax_minor' => 2310, 'total_minor' => 23310, 'vat_percent' => 11, 'non_pkp' => false]];
        $this->cbe = Cbe::create(['request_id' => $this->request->id, 'number' => 'CBE-PO-TEST', 'date' => today(),
            'status' => 'final', 'prepared_by' => $this->scm->id, 'selected_vendor_id' => $vendor->id,
            'finalized_at' => now(), 'final_minor' => 23310, 'vat_percent' => 11,
            'snapshot' => ['approval_required' => true, 'request' => ['number' => $this->request->number, 'company' => 'KPMOG'],
                'items' => [['id' => 45, 'name' => 'Barang uji', 'specification' => 'Spesifikasi PR']], 'quotes' => [$quote]]]);
        foreach (['prepared', 'acknowledged', 'crosschecked', 'approved'] as $position => $role) {
            $this->cbe->approvals()->create(['position' => $position, 'role' => $role, 'user_id' => $this->scm->id,
                'assigned_name' => 'Penguji', 'status' => $role === 'approved' ? 'pending' : 'signed']);
        }
    }

    private function form(string $number = 'PO/TEST/001'): array
    {
        return ['number' => $number, 'issued_at' => '2026-11-10', 'supplier_address' => 'Alamat awal',
            'order_contact' => 'Kontak vendor', 'delivery_terms' => 'Gudang HO', 'delivery_time' => '2 minggu',
            'payment_terms' => '30 hari', 'notes' => 'Catatan uji'];
    }

    private function approve(bool $completeLegal = true): void
    {
        $this->cbe->approvals()->where('role', 'approved')->update(['status' => 'signed', 'signed_name' => 'Penguji', 'acted_at' => now()]);
        if ($completeLegal) {
            $this->request->legalCase()->create(['cbe_id' => $this->cbe->id, 'vendor_id' => $this->cbe->selected_vendor_id,
                'scm_pic_id' => $this->scm->id, 'requested_at' => now(), 'completed_at' => now()]);
            $this->request->update(['status' => 'completed', 'completed_at' => now()]);
            $this->request->refresh();
        }
    }

    public function test_po_requires_approved_cbe_and_an_authorized_processor(): void
    {
        Livewire::actingAs($this->scm)->test(ScmProcurement::class)
            ->call('open', $this->request->id)->call('showTab', 'po')
            ->assertSee('PO dapat dibuat setelah seluruh persetujuan CBE')
            ->assertDontSee('Simpan draft PO');
        try {
            app(PurchaseOrders::class)->save($this->scm, $this->request->id, $this->request->version, $this->form());
            $this->fail('CBE belum disetujui.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('Approved By', $error->getMessage());
        }
        $this->approve();
        try {
            app(PurchaseOrders::class)->save($this->outsider, $this->request->id, $this->request->version, $this->form());
            $this->fail('Pengguna tanpa akses tidak boleh membuat PO.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        $this->assertDatabaseCount('scm_purchase_orders', 0);
    }

    public function test_po_keeps_approved_snapshot_and_reconciles_amounts(): void
    {
        $this->approve();
        $updated = app(PurchaseOrders::class)->save($this->scm, $this->request->id, $this->request->version, $this->form());
        $order = $updated->purchaseOrder;
        $this->assertSame('PO/TEST/001', $order->number);
        $this->assertSame('Vendor Pengujian', $order->snapshot['vendor']['name']);
        $this->assertSame('QT-123', $order->snapshot['quotation_number']);
        $this->assertSame('Spesifikasi PR', $order->snapshot['lines'][0]['specification']);
        $this->assertSame(23310, $order->snapshot['evaluation']['total_minor']);
        $this->assertSame(2500, $order->snapshot['extra_minor']);
        Vendor::findOrFail($this->cbe->selected_vendor_id)->update(['name' => 'Nama baru']);
        $updated = app(PurchaseOrders::class)->save($this->scm, $updated->id, $updated->version, $this->form('PO/TEST/001'));
        $this->assertSame('Vendor Pengujian', $updated->purchaseOrder->snapshot['vendor']['name']);
        $this->assertDatabaseCount('scm_purchase_orders', 1);
        $this->assertDatabaseHas('scm_audits', ['request_id' => $this->request->id, 'action' => 'ubah_draft_po']);
    }

    public function test_po_waits_until_the_selected_vendor_legal_review_is_complete(): void
    {
        $this->approve(false);
        Livewire::actingAs($this->scm)->test(ScmProcurement::class)
            ->call('open', $this->request->id)->call('showTab', 'po')
            ->assertSee('Selesaikan legalitas vendor terpilih melalui tombol Selesaikan pengadaan pada tab Legalitas sebelum membuat PO.')
            ->assertDontSee('Simpan draft PO');
        try {
            app(PurchaseOrders::class)->save($this->scm, $this->request->id, $this->request->version, $this->form());
            $this->fail('Legalitas belum selesai.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('Selesaikan legalitas', $error->getMessage());
        }
        $this->assertDatabaseCount('scm_purchase_orders', 0);
    }

    public function test_completed_legal_review_must_belong_to_the_selected_vendor(): void
    {
        $this->approve(false);
        $otherVendor = Vendor::create(['code' => 'PO-OTHER', 'name' => 'Vendor lain', 'provider_type' => 'barang',
            'created_by' => $this->scm->id, 'updated_by' => $this->scm->id]);
        $this->request->legalCase()->create(['cbe_id' => $this->cbe->id, 'vendor_id' => $otherVendor->id,
            'scm_pic_id' => $this->scm->id, 'requested_at' => now(), 'completed_at' => now()]);
        $this->request->update(['status' => 'completed', 'completed_at' => now()]);
        try {
            app(PurchaseOrders::class)->save($this->scm, $this->request->id, $this->request->version, $this->form());
            $this->fail('Legalitas vendor lain tidak cukup.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('Selesaikan legalitas vendor terpilih', $error->getMessage());
        }
        $this->assertDatabaseCount('scm_purchase_orders', 0);
    }

    public function test_po_requires_manual_number_and_current_request_version(): void
    {
        $this->approve();
        foreach ([[$this->request->version, ''], [99, 'PO/TEST/001']] as [$version, $number]) {
            try {
                app(PurchaseOrders::class)->save($this->scm, $this->request->id, $version, $this->form($number));
                $this->fail('Data yang salah tidak boleh tersimpan.');
            } catch (ValidationException $error) {
                $this->assertNotEmpty($error->errors());
            }
        }
        $this->assertDatabaseCount('scm_purchase_orders', 0);
    }

    public function test_po_rejects_duplicate_number_and_inconsistent_cbe_total(): void
    {
        $this->approve();
        app(PurchaseOrders::class)->save($this->scm, $this->request->id, $this->request->version, $this->form());
        $second = $this->request->replicate()->fill(['number' => 'PR-PO-TEST-2']);
        $second->save();
        $second = $second->fresh();
        $secondCbe = $this->cbe->replicate()->fill(['request_id' => $second->id, 'number' => 'CBE-PO-TEST-2']);
        $secondCbe->save();
        foreach ($this->cbe->approvals as $step) {
            $secondCbe->approvals()->create($step->only(['position', 'role', 'user_id', 'assigned_name', 'status', 'signed_name', 'acted_at']));
        }
        $second->legalCase()->create(['cbe_id' => $secondCbe->id, 'vendor_id' => $secondCbe->selected_vendor_id,
            'scm_pic_id' => $this->scm->id, 'requested_at' => now(), 'completed_at' => now()]);
        try {
            app(PurchaseOrders::class)->save($this->scm, $second->id, $second->version, $this->form('po/test/001'));
            $this->fail('Nomor PO yang sama harus ditolak.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('Nomor PO sudah digunakan', $error->getMessage());
        }
        DB::table('scm_cbes')->where('id', $secondCbe->id)->update(['final_minor' => 123]);
        try {
            app(PurchaseOrders::class)->save($this->scm, $second->id, $second->version, $this->form('PO/TEST/002'));
            $this->fail('Total yang tidak cocok harus ditolak.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('tidak cocok dengan CBE final', $error->getMessage());
        }
        $this->assertDatabaseCount('scm_purchase_orders', 1);
    }

    public function test_processor_can_save_manual_po_from_the_form(): void
    {
        $this->approve();
        Livewire::actingAs($this->scm)->test(ScmProcurement::class)
            ->call('open', $this->request->id)->call('showTab', 'po')
            ->set('purchaseOrderForm', $this->form())
            ->call('savePurchaseOrder')->assertHasNoErrors()->assertSee('PO/TEST/001');
        $this->assertDatabaseHas('scm_purchase_orders', ['request_id' => $this->request->id, 'number' => 'PO/TEST/001']);
    }

    public function test_processor_can_open_po_form_and_print_while_others_are_denied(): void
    {
        $this->approve();
        $updated = app(PurchaseOrders::class)->save($this->scm, $this->request->id, $this->request->version, $this->form());
        Livewire::actingAs($this->scm)->test(ScmProcurement::class)
            ->call('open', $updated->id)->call('showTab', 'po')
            ->assertSee('Purchase Order')->assertSee('PO/TEST/001')->assertSee('Buka PO untuk cetak / simpan PDF');
        $url = route('scm.purchase-order-print', $updated->purchaseOrder);
        $response = $this->actingAs($this->scm)->get($url)->assertOk()->assertSeeText('Vendor Pengujian')
            ->assertSeeText('PO/TEST/001')->assertSeeText('Rp 233,10')->assertSeeText('November 10, 2026')->assertDontSeeText('Belum ditandatangani')
            ->assertSeeText('Two hundred thirty-three rupiah and ten sen')
            ->assertSeeText('Terms & Conditions applied as attachment this Purchase Order')
            ->assertSeeText('KPM-FRM-058, 23 April 2024, Rev.2')->assertSee('aria-label="Paraf persetujuan PO"', false)
            ->assertSee('img/brands/kpmog-po-letterhead.png')->assertDontSee('class="scm-po-brand"', false)
            ->assertDontSee('class="scm-po-footer"', false);
        $this->assertSame(8, substr_count($response->getContent(), 'class="scm-po-amount-symbol"'));
        $this->actingAs($this->outsider)->get($url)->assertForbidden();
    }

    public function test_other_company_po_does_not_use_kpmog_letterhead(): void
    {
        $this->approve();
        $updated = app(PurchaseOrders::class)->save($this->scm, $this->request->id, $this->request->version, $this->form());
        $order = $updated->purchaseOrder;
        $order->update(['snapshot' => [...$order->snapshot, 'company' => 'APCA', 'company_name' => 'APCA']]);

        $this->actingAs($this->scm)->get(route('scm.purchase-order-print', $order))->assertOk()
            ->assertDontSee('kpmog-po-letterhead.png')->assertSee('img/brands/apca.png');
    }

    public function test_rupiah_amount_in_words_preserves_fractional_values(): void
    {
        $this->assertSame('Nol rupiah', AmountInWords::rupiah(0));
        $this->assertSame('Seribu satu rupiah satu sen', AmountInWords::rupiah(100101));
        $this->assertSame('Enam puluh empat juta sembilan ratus lima puluh tujuh ribu dua ratus rupiah', AmountInWords::rupiah(6495720000));
        $this->assertSame('Zero rupiah', AmountInWords::english(0, 'IDR'));
        $this->assertSame('Sixty-four million nine hundred fifty-seven thousand two hundred rupiah', AmountInWords::english(6495720000, 'IDR'));
        $this->assertSame('Twelve US dollars and one cent', AmountInWords::english(1201, 'USD'));
    }

    public function test_master_selects_two_buyers_for_wet_signature_and_preserves_saved_po_names(): void
    {
        $this->approve();
        $updated = app(PurchaseOrders::class)->save($this->scm, $this->request->id, $this->request->version, $this->form());
        $this->scm->directPermissions()->attach(Permission::where('code', 'scm.configure')->sole()->id);
        $admin = $this->scm->fresh();
        $buyer = User::factory()->create(['name' => 'Direktur Pengujian', 'is_admin' => true]);
        $secondBuyer = User::factory()->create(['name' => 'Direktur Utama Pengujian', 'is_admin' => true]);
        try {
            app(PoApprovals::class)->savePolicy($admin, ['company' => 'KPMOG', 'buyer_id' => $buyer->id, 'second_buyer_id' => $buyer->id, 'version' => 0]);
            $this->fail('Kedua penandatangan harus berbeda.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('second_buyer_id', $error->errors());
        }
        Livewire::actingAs($admin)->test(ScmProcurement::class)->call('switchSection', 'settings')
            ->set('approvalDocument', 'PO')->set('poApprovalPolicy.buyer_id', $buyer->id)
            ->set('poApprovalPolicy.buyer_position', 'Director')->set('poApprovalPolicy.second_buyer_id', $secondBuyer->id)
            ->set('poApprovalPolicy.second_buyer_position', 'President Director')->call('savePoApprovalPolicy')->assertHasNoErrors();
        $url = route('scm.purchase-order-print', $updated->purchaseOrder);
        $this->actingAs($admin)->get($url)->assertOk()->assertSeeText('Direktur Pengujian')->assertSeeText('Director')
            ->assertSeeText('Direktur Utama Pengujian')->assertSeeText('President Director')->assertDontSeeText('Belum ditandatangani');
        $updated = app(PurchaseOrders::class)->save($admin, $updated->id, $updated->version, $this->form());
        $other = User::factory()->create(['name' => 'Direktur Baru', 'is_admin' => true]);
        app(PoApprovals::class)->savePolicy($admin, ['company' => 'KPMOG', 'buyer_id' => $other->id, 'buyer_position' => 'Direktur',
            'second_buyer_id' => $secondBuyer->id, 'second_buyer_position' => 'President Director', 'version' => 1]);
        $this->get($url)->assertSeeText('Direktur Pengujian')->assertDontSeeText('Direktur Baru');
        $this->assertSame('Direktur Pengujian', $updated->purchaseOrder->snapshot['buyer_signer']['name']);
        $this->assertSame('Direktur Utama Pengujian', $updated->purchaseOrder->snapshot['second_buyer_signer']['name']);
        try {
            app(PoApprovals::class)->savePolicy($this->outsider, ['company' => 'KPMOG', 'buyer_id' => $other->id, 'version' => 2]);
            $this->fail('Akun tanpa izin tidak boleh mengubah Master Approval PO.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
    }

    public function test_po_remains_accessible_when_master_table_has_not_been_migrated(): void
    {
        $this->approve();
        $updated = app(PurchaseOrders::class)->save($this->scm, $this->request->id, $this->request->version, $this->form());
        Schema::drop('scm_po_approval_policies');

        Livewire::actingAs($this->scm)->test(ScmProcurement::class)->call('open', $updated->id)->call('showTab', 'po')
            ->assertHasNoErrors()->assertSee('PO/TEST/001');
        $response = $this->actingAs($this->scm)->get(route('scm.purchase-order-print', $updated->purchaseOrder))->assertOk()->assertSeeText('Nama / tanda tangan');
        $this->assertSame(2, substr_count($response->getContent(), 'class="scm-po-signature-empty"'));
        $updated = app(PurchaseOrders::class)->save($this->scm, $updated->id, $updated->version, $this->form());
        $this->assertSame('PO/TEST/001', $updated->purchaseOrder->number);
        $this->scm->directPermissions()->attach(Permission::where('code', 'scm.configure')->sole()->id);
        Livewire::actingAs($this->scm->fresh())->test(ScmProcurement::class)->call('switchSection', 'settings')
            ->set('approvalDocument', 'PO')->assertSee('Master Approval PO belum dipasang atau diperbarui di database.');
    }
}
