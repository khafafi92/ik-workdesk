<?php

namespace Tests\Feature\Scm;

use App\Filament\Pages\ScmProcurement;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Scm\Vendor;
use App\Models\Scm\VendorRegistration;
use App\Models\User;
use App\Services\Scm\VendorEnrollment;
use App\Services\Scm\VendorForm;
use App\Services\Scm\VendorPublicPortal;
use App\Services\Scm\Workflow;
use Database\Seeders\ScmSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PublicVendorPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $scm;

    private VendorEnrollment $enrollment;

    private VendorPublicPortal $portal;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('scm');
        $this->seed(ScmSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->scm = User::factory()->create(['is_admin' => false]);
        $department = Department::create(['code' => 'PUBLIC-VENDOR-QA', 'name' => 'SCM pengujian', 'is_active' => true]);
        Employee::create(['user_id' => $this->scm->id, 'department_id' => $department->id, 'name' => $this->scm->name, 'is_active' => true]);
        $this->scm->directPermissions()->attach(Permission::where('code', 'scm.process')->sole()->id);
        $this->enrollment = app(VendorEnrollment::class);
        $this->portal = app(VendorPublicPortal::class);
    }

    private function register(string $name = 'Vendor publik pengujian'): array
    {
        return $this->portal->register(['name' => $name, 'company' => 'KPMOG', 'provider_type' => 'keduanya']);
    }

    private function answers(int $step): array
    {
        $data = [];
        foreach (VendorForm::fields($step) as $key => $field) {
            $data[$key] = match ($field[1]) {
                'choice' => 'no', 'confirm' => '1', 'email' => 'vendor-pengujian@example.test', 'url' => '',
                'date' => today()->toDateString(), default => 'Isian pengujian '.$key,
            };
        }
        if ($step === 1) {
            $data['name'] = 'Vendor publik pengujian';
            $data['country'] = 'Indonesia';
            $data['non_pkp'] = 'yes';
        }

        return $data;
    }

    private function submit(VendorRegistration $record): VendorRegistration
    {
        foreach (range(1, 4) as $step) {
            $this->enrollment->saveStep($record, $record->fresh()->version, $step, $this->answers($step));
        }
        $record->refresh();
        foreach (VendorForm::requestedDocuments($record->answers, $record->context) as $code => $definition) {
            if (VendorForm::required($code, $record->answers, $record->context)) {
                $this->enrollment->upload($record, $record->fresh()->version, $code,
                    ['number' => 'UJI-00001', ...(VendorForm::documents()[$code][3] ? ['expires_at' => today()->addYear()->toDateString()] : [])],
                    UploadedFile::fake()->createWithContent('DOKUMEN-PENGUJIAN.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n"));
            }
        }
        $this->enrollment->saveStep($record, $record->fresh()->version, 5, $this->answers(5), true);

        return $record->fresh();
    }

    public function test_public_entry_is_available_from_login_and_registration_does_not_create_a_master_vendor(): void
    {
        $this->get(route('vendor.index'))->assertOk()->assertSee('Vendor baru')->assertSee('Vendor ID')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('login'))->assertSee(route('vendor.index'));
        $response = $this->post(route('vendor.register'), ['name' => 'Vendor baru pengujian', 'company' => 'KPMOG', 'provider_type' => 'barang']);
        $response->assertRedirect()->assertSessionHas('vendor_access_code');
        $code = session('vendor_access_code');
        $this->assertMatchesRegularExpression('/^VND-[A-F0-9]{8}(-[A-F0-9]{8}){3}$/', $code);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee($code);
        $record = VendorRegistration::sole();
        $this->assertNull($record->vendor_id);
        $this->assertNull($record->invited_by);
        $this->assertSame(0, Vendor::count());
        $this->assertArrayNotHasKey('code_hash', $record->portalAccess->toArray());
        $this->assertStringNotContainsString($code, json_encode(DB::table('scm_vendor_portal_accesses')->get()));
    }

    public function test_indonesia_address_uses_the_selected_kelurahan_and_rejects_a_mismatched_district(): void
    {
        [$record, $token] = $this->register();
        $answers = [...$this->answers(1), 'company_origin' => 'indonesia', 'city_code' => '31.74',
            'district_code' => '31.74.01', 'village_code' => '31.74.01.1001',
            'city' => '', 'province' => '', 'postcode' => '', 'country' => 'Singapore'];
        $this->post(route('vendor.portal.save', ['token' => $token, 'step' => 1]), [
            'version' => $record->version, 'answers' => $answers,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $record->refresh();
        $saved = $record->answers[1];
        $this->assertSame('Jakarta Selatan', $saved['city']);
        $this->assertSame('DKI Jakarta', $saved['province']);
        $this->assertSame('12820', $saved['postcode']);
        $this->assertSame('Indonesia', $saved['country']);
        $this->assertSame('Tebet Timur', $saved['village']);
        $this->post(route('vendor.portal.save', ['token' => $token, 'step' => 1]), [
            'version' => $record->version, 'answers' => [...$answers, 'district_code' => '31.71.01'],
        ])->assertRedirect()->assertSessionHasErrors('district');
        $this->assertSame($saved, $record->fresh()->answers[1]);
    }

    public function test_foreign_addresses_keep_their_country_and_do_not_use_indonesian_region_codes(): void
    {
        [$record, $token] = $this->register();
        $this->post(route('vendor.portal.save', ['token' => $token, 'step' => 1]), [
            'version' => $record->version, 'answers' => [...$this->answers(1), 'company_origin' => 'foreign',
                'document_basis' => 'company', 'city' => 'Singapore', 'province' => 'Singapore',
                'postcode' => '048616', 'country' => 'Singapore', 'city_code' => '31.74'],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $saved = $record->fresh()->answers[1];
        $this->assertSame('Singapore', $saved['country']);
        $this->assertSame('048616', $saved['postcode']);
        $this->assertArrayNotHasKey('city_code', $saved);
    }

    public function test_region_reference_is_available_without_exposing_vendor_data(): void
    {
        $response = $this->getJson(route('vendor.address', ['city' => '31.74']))->assertOk()
            ->assertJsonPath('province', 'DKI Jakarta')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame('12820', $response->json('districts')['31.74.01']['villages']['31.74.01.1001']['postcode']);
        $this->getJson(route('vendor.address', ['city' => '99.99']))->assertNotFound();
    }

    public function test_number_reopens_only_its_own_saved_data_and_never_reveals_other_vendor_files(): void
    {
        [$first, $firstToken, $firstCode] = $this->register('Vendor satu pengujian');
        [$other, $otherToken] = $this->register('Vendor dua pengujian');
        $this->enrollment->saveStep($first, 1, 1, $this->answers(1));
        [$reopened, $token] = $this->portal->resume(strtolower($firstCode));
        $this->assertSame($first->id, $reopened->id);
        $this->assertSame($firstToken, $token);
        $this->assertSame('Vendor publik pengujian', $reopened->answers[1]['name']);
        $this->get(route('vendor.portal', ['token' => $token, 'step' => 1]))->assertOk()->assertDontSee('Vendor dua pengujian');
        $this->enrollment->upload($other, 1, 'NPWP', ['number' => 'UJI-0002'], UploadedFile::fake()->createWithContent('pengujian.pdf', "%PDF-1.4\n%%EOF\n"));
        $file = $other->files()->sole();
        $this->get(route('vendor.portal.download', ['token' => $firstToken, 'file' => $file->id]))->assertNotFound();
        $this->get(route('vendor.portal.download', ['token' => $otherToken, 'file' => $file->id]))->assertOk();
        $this->post(route('vendor.resume'), ['access_code' => 'VND-'.str_repeat('0', 32)])->assertRedirect(route('vendor.index'))->assertSessionHasErrors('access_code');
    }

    public function test_public_submission_is_reviewed_before_creating_vendor_and_files_are_grouped_under_that_vendor(): void
    {
        [$record] = $this->register();
        $record = $this->submit($record);
        $this->assertSame(0, Vendor::count());
        Livewire::actingAs($this->scm)->test(ScmProcurement::class)->call('switchSection', 'vendors')->assertSee('Pendaftaran baru')->assertSee('Vendor publik pengujian');
        $this->actingAs($this->scm)->get(route('scm.vendor-review', $record))->assertOk();
        $this->enrollment->review($this->scm, $record->id, $record->version, 'accept', null);
        $record->refresh();
        $this->assertSame('accepted', $record->status);
        $this->assertSame(1, Vendor::count());
        $this->assertSame($record->vendor_id, $record->portalAccess->vendor_id);
        $this->assertTrue($record->vendor->documents()->exists());
        $this->assertSame('Vendor publik pengujian', $record->vendor->name);
    }

    public function test_accepted_vendor_can_update_with_the_same_number_without_overwriting_history_or_master(): void
    {
        [$record, $oldToken, $code] = $this->register();
        $record = $this->submit($record);
        $this->enrollment->review($this->scm, $record->id, $record->version, 'accept', null);
        $record->refresh();
        $snapshot = $record->submitted_snapshot;
        $vendorId = $record->vendor_id;
        $master = $record->vendor;
        app(Workflow::class)->saveVendor($this->scm, [...$master->getAttributes(), 'phone' => '081234567890'], $master->id, $master->version);
        [$draft, $newToken] = $this->portal->resume($code);
        $this->assertNotSame($record->id, $draft->id);
        $this->assertSame($vendorId, $draft->vendor_id);
        $this->assertSame('draft', $draft->status);
        $this->assertArrayNotHasKey(5, $draft->answers);
        $this->assertSame('081234567890', $draft->answers[1]['phone']);
        $this->assertSame($master->fresh()->version, $draft->vendor_version);
        $this->assertSame($record->files()->count(), $draft->files()->count());
        $this->assertSame([], array_intersect($record->files()->pluck('path')->all(), $draft->files()->pluck('path')->all()));
        $this->enrollment->saveStep($draft, $draft->version, 1, [...$this->answers(1), 'name' => 'Nama pembaruan pengujian']);
        $this->assertSame('Vendor publik pengujian', $record->vendor->fresh()->name);
        $this->assertSame($snapshot, $record->fresh()->submitted_snapshot);
        [$sameDraft, $sameToken] = $this->portal->resume($code);
        $this->assertSame($draft->id, $sameDraft->id);
        $this->assertSame($newToken, $sameToken);
        $this->get(route('vendor.portal', ['token' => $oldToken, 'step' => 1]))->assertOk();
    }

    public function test_scm_correction_can_be_fixed_using_the_same_number_and_revocation_blocks_all_public_access(): void
    {
        [$record, $token, $code] = $this->register();
        $record = $this->submit($record);
        $this->enrollment->review($this->scm, $record->id, $record->version, 'correction', 'Perbaiki dokumen sesuai catatan pemeriksaan.');
        [$reopened] = $this->portal->resume($code);
        $this->assertSame('correction', $reopened->status);
        $this->enrollment->saveStep($reopened, $reopened->version, 1, $this->answers(1));
        $this->enrollment->review($this->scm, $reopened->id, $reopened->fresh()->version, 'revoke', null);
        $this->get(route('vendor.portal', ['token' => $token, 'step' => 1]))->assertGone();
        $this->expectException(ValidationException::class);
        $this->portal->resume($code);
    }

    public function test_expired_public_form_can_be_resumed_and_submitted_form_stays_locked(): void
    {
        [$record, $token, $code] = $this->register();
        $record->update(['expires_at' => now()->subDay()]);
        $this->get(route('vendor.portal', ['token' => $token, 'step' => 1]))->assertGone();
        [$reopened] = $this->portal->resume($code);
        $this->assertTrue($reopened->expires_at->isFuture());
        $submitted = $this->submit($reopened);
        [$locked] = $this->portal->resume($code);
        $this->assertSame('submitted', $locked->status);
        $this->post(route('vendor.portal.save', ['token' => $token, 'step' => 1]), ['version' => $submitted->version, 'answers' => $this->answers(1)])
            ->assertConflict();
    }

    public function test_failed_import_rolls_back_new_vendor_and_access_link_without_losing_original_files(): void
    {
        [$record] = $this->register();
        $record = $this->submit($record);
        $originalPaths = $record->files()->pluck('path')->all();
        Storage::disk('scm')->put($originalPaths[1], 'Isi rusak untuk pengujian rollback.');
        try {
            $this->enrollment->review($this->scm, $record->id, $record->version, 'accept', null);
            $this->fail('Impor dengan isi file rusak seharusnya ditolak.');
        } catch (ValidationException) {
            $this->assertSame(0, Vendor::count());
            $this->assertNull($record->portalAccess->fresh()->vendor_id);
            $this->assertSame('submitted', $record->fresh()->status);
            $this->assertNull($record->fresh()->vendor_id);
            $this->assertEqualsCanonicalizing($originalPaths, Storage::disk('scm')->allFiles());
        }
    }
}
