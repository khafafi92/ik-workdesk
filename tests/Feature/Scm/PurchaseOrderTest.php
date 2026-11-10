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
use App\Services\Scm\PurchaseOrders;
use Database\Seeders\ScmSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            ->assertSee('Purchase Order')->assertSee('PO/TEST/001')->assertSee('Buka draft PO untuk cetak / simpan PDF');
        $url = route('scm.purchase-order-print', $updated->purchaseOrder);
        $this->actingAs($this->scm)->get($url)->assertOk()->assertSeeText('Vendor Pengujian')
            ->assertSeeText('PO/TEST/001')->assertSeeText('IDR 233,10')->assertSeeText('Belum ditandatangani');
        $this->actingAs($this->outsider)->get($url)->assertForbidden();
    }
}
