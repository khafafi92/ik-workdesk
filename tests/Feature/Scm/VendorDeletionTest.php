<?php

namespace Tests\Feature\Scm;

use App\Filament\Pages\ScmProcurement;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Scm\Procurement;
use App\Models\Scm\Vendor;
use App\Models\User;
use App\Services\Scm\VendorDeletion;
use Database\Seeders\ScmSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class VendorDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $scm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->seed(ScmSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $department = Department::create(['code' => 'DELETE-VENDOR', 'name' => 'SCM test', 'is_active' => true]);
        $this->scm = User::factory()->create(['is_admin' => false]);
        Employee::create(['user_id' => $this->scm->id, 'department_id' => $department->id, 'name' => 'SCM test', 'is_active' => true]);
        $this->scm->directPermissions()->attach(Permission::where('code', 'scm.process')->sole()->id);
        $this->scm = $this->scm->fresh();
    }

    private function vendor(): Vendor
    {
        return Vendor::create(['code' => 'DELETE-TEST', 'name' => 'Vendor test', 'provider_type' => 'barang',
            'is_active' => true, 'created_by' => $this->scm->id, 'updated_by' => $this->scm->id])->fresh();
    }

    public function test_unused_vendor_can_be_deleted_from_master_and_audited(): void
    {
        $vendor = $this->vendor();
        Livewire::actingAs($this->scm)->test(ScmProcurement::class)
            ->call('switchSection', 'vendors')->assertSee('Hapus')
            ->call('deleteVendor', $vendor->id, $vendor->version)->assertHasNoErrors()->assertDontSee('DELETE-TEST');
        $this->assertNotNull($vendor->fresh()->archived_at);
        $this->assertFalse($vendor->fresh()->is_active);
        $this->assertDatabaseHas('scm_audits', ['entity_id' => $vendor->id, 'action' => 'hapus_vendor']);
    }

    public function test_vendor_with_registration_is_preserved(): void
    {
        $vendor = $this->vendor();
        DB::table('scm_vendor_registrations')->insert(['vendor_id' => $vendor->id, 'invited_by' => $this->scm->id,
            'company' => 'KPMOG', 'token_hash' => str_repeat('a', 64), 'vendor_version' => $vendor->version,
            'context' => '{}', 'expires_at' => now()->addDay()]);
        Livewire::actingAs($this->scm)->test(ScmProcurement::class)->call('switchSection', 'vendors')
            ->call('deleteVendor', $vendor->id, $vendor->version)->assertHasNoErrors()->assertDontSee('DELETE-TEST');
        $this->assertNotNull($vendor->fresh()->archived_at);
        $this->assertDatabaseHas('scm_vendor_registrations', ['vendor_id' => $vendor->id]);
    }

    public function test_vendor_linked_to_procurement_cannot_be_deleted(): void
    {
        $vendor = $this->vendor();
        $department = Department::where('code', 'DELETE-VENDOR')->sole();
        $request = Procurement::create(['company' => 'KPMOG', 'number' => 'PR-DELETE-TEST',
            'kind' => 'PR', 'need_type' => 'PROJECT', 'date' => today(), 'requester_id' => $this->scm->id,
            'department_id' => $department->id, 'title' => 'Pengujian', 'reason' => 'Uji hapus', 'status' => 'sourcing']);
        DB::table('scm_candidates')->insert(['request_id' => $request->id, 'vendor_id' => $vendor->id]);
        Livewire::actingAs($this->scm)->test(ScmProcurement::class)->call('switchSection', 'vendors')
            ->call('deleteVendor', $vendor->id, $vendor->version)->assertHasErrors(['scm']);
        $this->assertDatabaseHas('scm_vendors', ['id' => $vendor->id]);
        $this->assertDatabaseHas('scm_candidates', ['vendor_id' => $vendor->id, 'request_id' => $request->id]);
    }

    public function test_stale_version_cannot_be_deleted(): void
    {
        $vendor = $this->vendor();
        $this->expectException(ValidationException::class);
        app(VendorDeletion::class)->delete($this->scm, $vendor->id, $vendor->version + 1);
    }

    public function test_unauthorized_user_cannot_delete_vendor(): void
    {
        $vendor = $this->vendor();
        $this->expectException(HttpException::class);
        app(VendorDeletion::class)->delete(User::factory()->create(['is_admin' => false]), $vendor->id, $vendor->version);
    }
}
