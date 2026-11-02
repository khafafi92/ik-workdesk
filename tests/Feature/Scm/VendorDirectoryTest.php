<?php

namespace Tests\Feature\Scm;

use App\Filament\Pages\ScmVendorRegistrations;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Scm\Vendor;
use App\Models\Scm\VendorRegistration;
use App\Models\User;
use App\Services\Scm\VendorPublicPortal;
use Database\Seeders\ScmSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class VendorDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private User $scm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->seed(ScmSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->scm = User::factory()->create(['is_admin' => false]);
        $department = Department::create(['code' => 'VENDOR-DIRECTORY-QA', 'name' => 'SCM pengujian', 'is_active' => true]);
        Employee::create(['user_id' => $this->scm->id, 'department_id' => $department->id, 'name' => $this->scm->name, 'is_active' => true]);
        $this->scm->directPermissions()->attach(Permission::where('code', 'scm.process')->sole()->id);
    }

    private function registration(string $name, string $company = 'KPMOG'): VendorRegistration
    {
        return app(VendorPublicPortal::class)->register(['name' => $name, 'company' => $company, 'provider_type' => 'keduanya'])[0];
    }

    public function test_a_saved_draft_is_visible_without_being_created_as_a_master_vendor_or_exposing_secrets(): void
    {
        $record = $this->registration('PT Cakrawala PENGUJIAN');
        $record->forceFill(['answers' => [1 => ['name' => 'PT Cakrawala PENGUJIAN', 'email' => 'qa@example.test', 'phone' => '000-PENGUJIAN'], 2 => ['quality_system' => 'no']]])->save();
        $before = $record->fresh()->getAttributes();
        $this->actingAs($this->scm)->get(ScmVendorRegistrations::getUrl())->assertOk()
            ->assertSee('PT Cakrawala PENGUJIAN')->assertSee('qa@example.test')->assertSee('Draft / belum dikirim')
            ->assertSee('2 dari 5 bagian')->assertSee(route('scm.vendor-review', ['registration' => $record, 'dari' => 'data-vendor']))
            ->assertDontSee($record->token_hash)->assertDontSee($record->portalAccess->code_hash);
        $this->assertSame($before, $record->fresh()->getAttributes());
        $this->assertSame(0, Vendor::count());
    }

    public function test_all_statuses_are_visible_and_search_and_company_filters_include_drafts(): void
    {
        $draft = $this->registration('Vendor Alpha PENGUJIAN');
        $submitted = $this->registration('Vendor Beta PENGUJIAN', 'APCA');
        $submitted->update(['status' => 'submitted']);
        $accepted = $this->registration('Vendor Gamma PENGUJIAN');
        $accepted->update(['status' => 'accepted']);
        $correction = $this->registration('Vendor Delta PENGUJIAN');
        $correction->update(['status' => 'correction']);
        $this->actingAs($this->scm);
        Livewire::test(ScmVendorRegistrations::class)->assertSee('Vendor Alpha PENGUJIAN')->assertSee('Vendor Beta PENGUJIAN')
            ->assertSee('Vendor Gamma PENGUJIAN')->assertSee('Vendor Delta PENGUJIAN')
            ->set('search', 'alpha')->assertSee('Vendor Alpha PENGUJIAN')->assertDontSee('Vendor Beta PENGUJIAN')
            ->set('search', '')->set('companyFilter', 'APCA')->assertSee('Vendor Beta PENGUJIAN')->assertDontSee('Vendor Alpha PENGUJIAN')
            ->call('clearFilters')->set('statusFilter', 'draft')->assertSee('Vendor Alpha PENGUJIAN')->assertDontSee('Vendor Beta PENGUJIAN')
            ->set('statusFilter', 'submitted')->assertSee('Vendor Beta PENGUJIAN')->assertDontSee('Vendor Gamma PENGUJIAN')
            ->call('clearFilters')->assertSee('Vendor Gamma PENGUJIAN')
            ->set('search', 'tidak-ditemukan')->assertSee('Belum ada pendaftaran vendor sesuai pencarian');
    }

    public function test_search_can_find_a_saved_email_and_a_linked_master_code(): void
    {
        $record = $this->registration('Vendor Email PENGUJIAN');
        $record->update(['answers' => [1 => ['name' => 'Vendor Email PENGUJIAN', 'email' => 'vendor-saved@example.test']]]);
        $vendor = Vendor::create(['code' => 'VENDOR-SEARCH-QA', 'name' => 'Master PENGUJIAN', 'provider_type' => 'barang', 'is_active' => true,
            'created_by' => $this->scm->id, 'updated_by' => $this->scm->id]);
        $record->update(['vendor_id' => $vendor->id]);
        $this->actingAs($this->scm);
        Livewire::test(ScmVendorRegistrations::class)->set('search', 'VENDOR-SAVED@EXAMPLE.TEST')->assertSee('Vendor Email PENGUJIAN')
            ->set('search', 'vendor-search-qa')->assertSee('Vendor Email PENGUJIAN');
    }

    public function test_only_the_latest_form_for_a_portal_access_is_listed_and_revoked_data_remains_visible(): void
    {
        $older = $this->registration('Nama lama PENGUJIAN');
        $latest = $older->replicate();
        $latest->token_hash = hash('sha256', 'vendor-directory-latest-test');
        $latest->answers = [1 => ['name' => 'Nama terbaru PENGUJIAN']];
        $latest->save();
        $older->portalAccess->update(['revoked_at' => now()]);
        $this->actingAs($this->scm);
        Livewire::test(ScmVendorRegistrations::class)->assertSee('Nama terbaru PENGUJIAN')->assertDontSee('Nama lama PENGUJIAN')
            ->assertSee('Akses dicabut')->set('statusFilter', 'draft')->assertDontSee('Nama terbaru PENGUJIAN')
            ->set('statusFilter', 'revoked')->assertSee('Nama terbaru PENGUJIAN');
    }

    public function test_filtering_from_a_later_page_returns_to_the_first_page(): void
    {
        foreach (range(1, 16) as $number) {
            $this->registration('Vendor PENGUJIAN '.$number);
        }
        $this->actingAs($this->scm);
        Livewire::test(ScmVendorRegistrations::class)->call('gotoPage', 2)->set('search', 'PENGUJIAN 16')
            ->assertSee('Vendor PENGUJIAN 16')->assertSet('paginators.page', 1);
    }

    public function test_the_directory_and_draft_detail_require_existing_scm_processing_access(): void
    {
        $record = $this->registration('Vendor privat PENGUJIAN');
        $this->get(ScmVendorRegistrations::getUrl())->assertRedirect();
        $outsider = User::factory()->create(['is_admin' => false]);
        $this->actingAs($outsider)->get(ScmVendorRegistrations::getUrl())->assertForbidden();
        $this->get(route('scm.vendor-review', ['registration' => $record, 'dari' => 'data-vendor']))->assertForbidden();
        $outsider->directPermissions()->attach(Permission::where('code', 'scm.configure')->sole()->id);
        $this->actingAs($outsider->fresh())->get(ScmVendorRegistrations::getUrl())->assertForbidden();
        $this->actingAs($this->scm)->get(route('scm.vendor-review', ['registration' => $record, 'dari' => 'data-vendor']))
            ->assertOk()->assertSee('Kembali ke Data Vendor')->assertDontSee('Terima profil dan dokumen ke master');
        $this->scm->employee->update(['is_active' => false]);
        $this->actingAs($this->scm->fresh())->get(ScmVendorRegistrations::getUrl())->assertForbidden();
        $this->assertSame(0, DB::table('scm_vendors')->count());
    }

    public function test_superuser_sees_encrypted_portal_id_and_can_use_it_to_open_existing_draft(): void
    {
        [$record, $token, $code] = app(VendorPublicPortal::class)->register(['name' => 'Vendor ID PENGUJIAN', 'company' => 'KPMOG', 'provider_type' => 'jasa']);
        $owner = User::factory()->create(['is_admin' => true]);
        $stored = DB::table('scm_vendor_portal_accesses')->where('id', $record->portal_access_id)->value('access_code');
        $this->assertNotSame($code, $stored);
        $this->assertStringNotContainsString($code, $stored);
        $this->assertArrayNotHasKey('access_code', $record->portalAccess->toArray());
        $response = $this->actingAs($owner)->get(ScmVendorRegistrations::getUrl())->assertOk()->assertSee('Vendor ID Portal')->assertSee($code)
            ->assertSee('Form #'.$record->id)->assertSee('Belum terbit; menunggu penerimaan SCM.');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->post(route('vendor.resume'), ['access_code' => $code])->assertRedirect(route('vendor.portal', ['token' => $token, 'step' => 1]));
        $this->actingAs($this->scm)->get(ScmVendorRegistrations::getUrl())->assertOk()->assertDontSee($code)->assertDontSee('Terbitkan Vendor ID pengganti');
        Livewire::actingAs($owner)->test(ScmVendorRegistrations::class)->set('search', 'Form #'.$record->id)->assertSee('Vendor ID PENGUJIAN');
    }

    public function test_legacy_id_stays_valid_until_explicit_reissue_and_reissue_keeps_answers_and_files(): void
    {
        Storage::fake('scm');
        [$record, $oldToken, $oldCode] = app(VendorPublicPortal::class)->register(['name' => 'Vendor lama PENGUJIAN', 'company' => 'KPMOG', 'provider_type' => 'barang']);
        $record->portalAccess->update(['access_code' => null]);
        Storage::disk('scm')->put('vendor-registration/test.pdf', '%PDF-1.4');
        $file = $record->files()->create(['code' => 'NPWP', 'number' => 'DEMO', 'path' => 'vendor-registration/test.pdf', 'original_name' => 'test.pdf', 'mime' => 'application/pdf', 'size' => 8]);
        $before = $record->fresh()->only(['answers', 'context', 'status', 'vendor_id', 'company', 'expires_at']);
        $owner = User::factory()->create(['is_admin' => true]);
        $this->actingAs($owner)->get(ScmVendorRegistrations::getUrl())->assertSee('Terbitkan Vendor ID pengganti')->assertDontSee($oldCode);
        $this->assertSame($record->id, app(VendorPublicPortal::class)->resume($oldCode)[0]->id);
        Livewire::actingAs($owner)->test(ScmVendorRegistrations::class)->call('reissueVendorId', $record->id, $record->version)->assertHasNoErrors()->assertSee('Vendor ID Portal');
        $this->assertSame($before['answers'], $record->fresh()->answers);
        $this->assertSame($before['context'], $record->fresh()->context);
        $this->assertSame($before['status'], $record->fresh()->status);
        $this->assertSame($before['vendor_id'], $record->fresh()->vendor_id);
        $this->assertSame($before['company'], $record->fresh()->company);
        $this->assertSame($file->id, $record->files()->sole()->id);
        Storage::disk('scm')->assertExists($file->path);
        $newCode = $record->portalAccess()->first()->access_code;
        $this->assertNotSame($oldCode, $newCode);
        $this->assertSame($record->id, app(VendorPublicPortal::class)->resume($newCode)[0]->id);
        $this->get(route('vendor.portal', ['token' => $oldToken, 'step' => 1]))->assertNotFound();
        $audit = DB::table('scm_vendor_registration_events')->where('action', 'portal_access_reissued')->sole();
        $this->assertSame($owner->id, $audit->actor_id);
        $this->assertStringNotContainsString($newCode, $audit->details);
        $this->expectException(ValidationException::class);
        app(VendorPublicPortal::class)->resume($oldCode);
    }

    public function test_ordinary_scm_and_inactive_superuser_cannot_reissue_vendor_id(): void
    {
        $record = $this->registration('Vendor dilindungi PENGUJIAN');
        $before = $record->portalAccess->getRawOriginal();
        Livewire::actingAs($this->scm)->test(ScmVendorRegistrations::class)->call('reissueVendorId', $record->id, $record->version)->assertForbidden();
        $this->scm->update(['is_admin' => true]);
        $this->scm->employee->update(['is_active' => false]);
        try {
            app(VendorPublicPortal::class)->reissueAccessCode($this->scm->fresh(), $record->id, $record->version);
            $this->fail('Inactive superuser must be rejected.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        $this->assertSame($before, $record->portalAccess()->first()->getRawOriginal());
    }

    public function test_reissue_rejects_stale_historical_and_revoked_forms_without_changing_code(): void
    {
        $owner = User::factory()->create(['is_admin' => true]);
        $record = $this->registration('Vendor versi PENGUJIAN');
        $latest = $record->replicate();
        $latest->token_hash = hash('sha256', 'vendor-reissue-latest');
        $latest->save();
        $this->actingAs($owner);
        $before = $record->portalAccess->getRawOriginal();
        Livewire::test(ScmVendorRegistrations::class)->call('reissueVendorId', $record->id, $record->version)->assertHasErrors('vendorAccess');
        Livewire::test(ScmVendorRegistrations::class)->call('reissueVendorId', $latest->id, $latest->version + 1)->assertHasErrors('vendorAccess');
        $record->portalAccess->update(['revoked_at' => now()]);
        Livewire::test(ScmVendorRegistrations::class)->assertDontSee('Vendor ID Portal')->call('reissueVendorId', $latest->id, $latest->version)->assertHasErrors('vendorAccess');
        $this->assertSame($before['code_hash'], $record->portalAccess()->first()->code_hash);
    }
}
