<?php

namespace Tests\Feature\Scm;

use App\Filament\Pages\ScmProcurement;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Scm\Vendor;
use App\Models\Scm\VendorDocument;
use App\Models\Scm\VendorRegistration;
use App\Models\User;
use App\Services\Scm\VendorEnrollment;
use App\Services\Scm\VendorForm;
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

class VendorPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $scm;

    private Vendor $vendor;

    private VendorEnrollment $enrollment;

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
        $this->scm = User::factory()->create();
        $department = Department::create(['code' => 'VENDOR-QA', 'name' => 'SCM DEMO', 'is_active' => true]);
        Employee::create(['user_id' => $this->scm->id, 'department_id' => $department->id, 'name' => $this->scm->name, 'is_active' => true]);
        $this->scm->directPermissions()->attach(Permission::where('code', 'scm.process')->sole());
        $this->vendor = app(Workflow::class)->saveVendor($this->scm, ['code' => 'PORTAL-DEMO', 'name' => 'Vendor DEMO sebelum form', 'provider_type' => 'keduanya', 'is_active' => true]);
        $this->enrollment = app(VendorEnrollment::class);
    }

    private function invite(array $context = []): array
    {
        [$registration, $url] = $this->enrollment->invite($this->scm, $this->vendor->id,
            [...['company' => 'KPMOG', 'is_construction' => false, 'amount' => '', 'duration_months' => ''], ...$context]);
        preg_match('~/form-005/([a-f0-9]{64})/~', $url, $matches);

        return [$registration, $matches[1]];
    }

    private function answers(int $step): array
    {
        $data = [];
        foreach (VendorForm::fields($step) as $key => $field) {
            $data[$key] = match ($field[1]) {
                'choice' => 'no', 'confirm' => '1', 'email' => 'demo@example.test', 'url' => '',
                'date' => today()->toDateString(), default => 'DEMO isian '.$key,
            };
        }
        if ($step === 1) {
            $data['country'] = 'Indonesia';
            $data['non_pkp'] = 'yes';
        }

        return $data;
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('DEMO-dokumen.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n");
    }

    private function fill(VendorRegistration $registration, bool $files = true): VendorRegistration
    {
        foreach (range(1, 4) as $step) {
            $this->enrollment->saveStep($registration, $registration->fresh()->version, $step, $this->answers($step));
        }
        if ($files) {
            $registration = $registration->fresh();
            foreach (VendorForm::documents() as $code => $definition) {
                if (VendorForm::required($code, $registration->answers, $registration->context)) {
                    $this->enrollment->upload($registration, $registration->fresh()->version, $code,
                        ['number' => 'DEMO-00001', ...($definition[3] ? ['expires_at' => today()->addYear()->toDateString()] : [])], $this->pdf());
                }
            }
        }

        return $registration->fresh();
    }

    public function test_five_guest_pages_are_scoped_to_a_hashed_expiring_invitation(): void
    {
        [$registration, $token] = $this->invite();
        $this->assertNotSame($token, $registration->getRawOriginal('token_hash'));
        foreach (VendorForm::TITLES as $step => $title) {
            $this->get(route('vendor.portal', ['token' => $token, 'step' => $step]))->assertOk()
                ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer')
                ->assertSee($title)->assertDontSee('Antrean SCM');
        }
        $this->get(route('vendor.portal', ['token' => str_repeat('0', 64), 'step' => 1]))->assertNotFound();
        $this->get(route('scm.vendor-review', $registration))->assertRedirect(route('login'));
        $registration->update(['expires_at' => now()->subMinute()]);
        $this->get(route('vendor.portal', ['token' => $token, 'step' => 1]))->assertGone()->assertSee('Hubungi SCM')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_renewal_revokes_the_previous_token_and_does_not_send_messages(): void
    {
        [$old, $oldToken] = $this->invite();
        [$new, $newToken] = $this->invite();
        $this->assertNotSame($oldToken, $newToken);
        $this->assertNotNull($old->fresh()->revoked_at);
        $this->get(route('vendor.portal', ['token' => $oldToken, 'step' => 1]))->assertGone();
        $this->get(route('vendor.portal', ['token' => $newToken, 'step' => 1]))->assertOk();
        $this->assertDatabaseCount('scm_vendor_registrations', 2);
    }

    public function test_form_updates_are_whitelisted_and_stale_versions_cannot_overwrite_data(): void
    {
        [$registration, $token] = $this->invite();
        $url = route('vendor.portal.save', ['token' => $token, 'step' => 1]);
        $this->post($url, ['version' => 1, 'answers' => [...$this->answers(1), 'is_active' => false, 'vendor_id' => 999, 'code' => 'HACK']])->assertRedirect();
        $registration = $registration->fresh();
        $this->assertArrayNotHasKey('is_active', $registration->answers[1]);
        $this->assertSame('Vendor DEMO sebelum form', $this->vendor->fresh()->name);
        $this->post($url, ['version' => 1, 'answers' => [...$this->answers(1), 'name' => 'Tertimpa']])->assertSessionHasErrors('version');
        $this->assertSame('DEMO isian name', $registration->fresh()->answers[1]['name']);
    }

    public function test_only_four_indonesia_documents_are_required_and_other_documents_follow_context_as_optional(): void
    {
        $answers = [1 => ['has_amendments' => 'no', 'non_pkp' => 'yes']];
        $this->assertTrue(VendorForm::required('KTP', $answers, []));
        $this->assertTrue(VendorForm::required('NPWP', $answers, []));
        $this->assertTrue(VendorForm::required('AKTA_PENDIRIAN', $answers, []));
        $this->assertTrue(VendorForm::required('IZIN_USAHA', $answers, []));
        $this->assertFalse(VendorForm::required('PKP', $answers, []));
        $this->assertFalse(VendorForm::required('AKTA_PERUBAHAN', $answers, []));
        foreach (['PERNYATAAN_HUKUM', 'REFERENSI_BANK'] as $code) {
            $this->assertFalse(VendorForm::required($code, $answers, ['is_construction' => true, 'amount_minor' => 10000000000, 'duration_months' => 4]));
            $this->assertFalse(VendorForm::required($code, $answers, ['is_construction' => true, 'amount_minor' => 10000000001, 'duration_months' => 3]));
            $this->assertFalse(VendorForm::required($code, $answers, ['is_construction' => false, 'amount_minor' => 10000000001, 'duration_months' => 4]));
            $context = ['is_construction' => true, 'amount_minor' => 10000000001, 'duration_months' => 4];
            $this->assertFalse(VendorForm::required($code, $answers, $context));
            $this->assertArrayHasKey($code, VendorForm::requestedDocuments($answers, $context));
        }
        $answers[1] = ['has_amendments' => 'yes', 'non_pkp' => 'no'];
        $this->assertFalse(VendorForm::required('PKP', $answers, []));
        $this->assertFalse(VendorForm::required('AKTA_PERUBAHAN', $answers, []));
        $this->assertArrayHasKey('PKP', VendorForm::requestedDocuments($answers, []));
        $this->assertArrayHasKey('AKTA_PERUBAHAN', VendorForm::requestedDocuments($answers, []));
    }

    public function test_files_are_private_scoped_versioned_and_validated_by_content(): void
    {
        [$registration, $token] = $this->invite();
        $upload = route('vendor.portal.upload', ['token' => $token, 'code' => 'KTP']);
        $this->post($upload, ['version' => 1, 'file' => UploadedFile::fake()->createWithContent('DEMO-fake.png', "%PDF-1.4\n%%EOF")])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('scm_vendor_registration_files', 0);
        $this->post($upload, ['version' => 1, 'file' => $this->pdf()])->assertRedirect();
        $first = $registration->files()->sole();
        $this->get(route('vendor.portal.download', ['token' => $token, 'file' => $first->id]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->post($upload, ['version' => $registration->fresh()->version, 'file' => $this->pdf()])->assertRedirect();
        $this->assertDatabaseCount('scm_vendor_registration_files', 2);
        Storage::disk('scm')->assertExists($first->path);
        [$other, $otherToken] = $this->invite();
        $this->get(route('vendor.portal.download', ['token' => $otherToken, 'file' => $first->id]))->assertNotFound();
    }

    public function test_submit_requires_all_sections_and_legal_files_then_locks_vendor_edits(): void
    {
        [$registration, $token] = $this->invite();
        $url = route('vendor.portal.save', ['token' => $token, 'step' => 5]);
        $this->post($url, ['version' => 1, 'intent' => 'submit', 'answers' => $this->answers(5)])->assertSessionHasErrors('form');
        $registration = $this->fill($registration, false);
        $this->post($url, ['version' => $registration->version, 'intent' => 'submit', 'answers' => $this->answers(5)])->assertSessionHasErrors('documents');
        $registration = $this->fill($registration);
        $this->post($url, ['version' => $registration->version, 'intent' => 'submit', 'answers' => $this->answers(5)])->assertRedirect();
        $registration = $registration->fresh();
        $this->assertSame('submitted', $registration->status);
        $this->assertSame('DEMO isian name', $registration->submitted_snapshot['answers'][1]['name']);
        $this->assertDatabaseCount('scm_vendor_documents', 0);
        $this->post(route('vendor.portal.save', ['token' => $token, 'step' => 1]), ['version' => $registration->version, 'answers' => $this->answers(1)])->assertConflict();
        $this->get(route('vendor.portal', ['token' => $token, 'step' => 1]))->assertOk()->assertDontSee('>Simpan jawaban bagian ini</button>', false);
    }

    public function test_scm_can_request_correction_and_accept_without_changing_vendor_access_or_original_files(): void
    {
        [$registration, $token] = $this->invite();
        $registration = $this->fill($registration);
        $this->enrollment->saveStep($registration, $registration->version, 5, $this->answers(5), true);
        $registration = $registration->fresh();
        $fileCount = $registration->files()->count();
        $this->actingAs($this->scm)->post(route('scm.vendor-decide', $registration), ['version' => $registration->version,
            'action' => 'correction', 'notes' => 'DEMO: mohon periksa kembali data.'])->assertRedirect();
        $this->assertSame('correction', $registration->fresh()->status);
        $this->enrollment->saveStep($registration, $registration->fresh()->version, 5, $this->answers(5), true);
        $this->post(route('scm.vendor-decide', $registration), ['version' => $registration->fresh()->version, 'action' => 'accept'])->assertRedirect();
        $this->assertSame('accepted', $registration->fresh()->status);
        $this->assertSame('DEMO isian name', $this->vendor->fresh()->name);
        $this->assertSame('PORTAL-DEMO', $this->vendor->fresh()->code);
        $this->assertTrue($this->vendor->fresh()->is_active);
        $this->assertSame($fileCount, VendorDocument::where('vendor_id', $this->vendor->id)->count());
        foreach ($registration->files()->get() as $file) {
            Storage::disk('scm')->assertExists($file->path);
        }
        $this->assertSame(2, DB::table('scm_vendor_registration_events')->where('registration_id', $registration->id)->where('action', 'submitted')->count());
        $this->enrollment->review($this->scm, $registration->id, $registration->fresh()->version, 'revoke', null);
        $this->get(route('vendor.portal', ['token' => $token, 'step' => 1]))->assertGone();
    }

    public function test_non_scm_cannot_review_or_receive_and_changed_master_is_not_overwritten(): void
    {
        [$registration] = $this->invite();
        $registration = $this->fill($registration);
        $this->enrollment->saveStep($registration, $registration->version, 5, $this->answers(5), true);
        $other = User::factory()->create(['is_admin' => true]);
        $this->actingAs($other)->get(route('scm.vendor-review', $registration))->assertForbidden();
        $this->post(route('scm.vendor-decide', $registration), ['version' => $registration->fresh()->version, 'action' => 'accept'])->assertForbidden();
        $this->vendor->update(['name' => 'DEMO perubahan internal', 'version' => $this->vendor->version + 1]);
        $this->actingAs($this->scm)->post(route('scm.vendor-decide', $registration), ['version' => $registration->fresh()->version, 'action' => 'accept'])->assertSessionHasErrors('review');
        $this->assertSame('DEMO perubahan internal', $this->vendor->fresh()->name);
        $this->assertDatabaseCount('scm_vendor_documents', 0);
    }

    public function test_construction_context_is_preserved_and_extra_documents_are_optional(): void
    {
        [$registration, $token] = $this->invite(['is_construction' => true, 'amount' => '100000001', 'duration_months' => 4]);
        $registration = $this->fill($registration, false);
        foreach (VendorForm::documents() as $code => $definition) {
            if (VendorForm::required($code, $registration->answers, $registration->context) && ! in_array($code, ['PERNYATAAN_HUKUM', 'REFERENSI_BANK'])) {
                $this->enrollment->upload($registration, $registration->fresh()->version, $code,
                    $definition[3] ? ['expires_at' => today()->addYear()->toDateString()] : [], $this->pdf());
            }
        }
        $url = route('vendor.portal.save', ['token' => $token, 'step' => 5]);
        $this->post($url, ['version' => $registration->fresh()->version, 'intent' => 'submit', 'context' => ['is_construction' => false],
            'answers' => $this->answers(5)])->assertSessionHasNoErrors();
        $this->assertTrue($registration->fresh()->context['is_construction']);
        $this->assertSame('submitted', $registration->fresh()->status);
    }

    public function test_optional_leader_contacts_and_expired_certificates_can_be_saved_submitted_and_imported_with_warnings(): void
    {
        [$registration, $token] = $this->invite();
        $registration = $this->fill($registration);
        $this->enrollment->saveStep($registration, $registration->version, 1, [...$this->answers(1), 'ceo_email' => '', 'ceo_phone' => '']);
        $this->enrollment->saveStep($registration, $registration->fresh()->version, 2, [...$this->answers(2), 'quality_certified' => 'yes',
            'certifying_body' => 'BADAN SERTIFIKASI PENGUJIAN', 'quality_expiry' => today()->subYear()->toDateString()]);
        foreach (['IZIN_USAHA', 'SERTIFIKAT_ASOSIASI'] as $code) {
            $this->enrollment->upload($registration, $registration->fresh()->version, $code, ['expires_at' => today()->subDay()->toDateString()], $this->pdf());
        }
        $this->enrollment->upload($registration, $registration->fresh()->version, 'COMPANY_PROFILE', [], $this->pdf());
        $registration->refresh();
        $this->assertNull($registration->answers[1]['ceo_email']);
        $this->assertNull($registration->answers[1]['ceo_phone']);
        $checklist = $this->enrollment->submissionChecklist($registration);
        $this->assertSame([], $checklist[1]['documents']);
        $this->assertSame([], $checklist[2]['answers']);
        $this->assertCount(2, $checklist[1]['warnings']);
        $this->assertCount(1, $checklist[2]['warnings']);
        $this->get(route('vendor.portal', ['token' => $token, 'step' => 1]))->assertOk()->assertSee('PT. KPMOG atau APCA Group?')->assertSee('Kedaluwarsa · tetap tersimpan');
        $this->get(route('vendor.portal', ['token' => $token, 'step' => 2]))->assertOk()->assertSee('vp-expired')->assertSee('Data tetap dapat disimpan dan dikirim.');
        $this->enrollment->saveStep($registration, $registration->version, 5, $this->answers(5), true);
        $this->enrollment->review($this->scm, $registration->id, $registration->fresh()->version, 'accept', null);
        $this->assertSame('accepted', $registration->fresh()->status);
        $this->assertSame(6, VendorDocument::where('vendor_id', $this->vendor->id)->count());
        $this->assertSame(2, VendorDocument::where('vendor_id', $this->vendor->id)->whereDate('expires_at', '<', today())->count());
        $this->actingAs($this->scm)->get(route('scm.vendor-review', $registration))->assertOk()->assertSee('vp-expired')->assertSee('Kedaluwarsa; berkas tetap diterima.')->assertSee('Opsional');
    }

    public function test_each_of_the_four_required_documents_is_still_enforced_and_optional_leader_email_is_validated_when_supplied(): void
    {
        [$registration] = $this->invite();
        $registration = $this->fill($registration);
        foreach (VendorForm::REQUIRED_INDONESIA as $code) {
            $files = $this->enrollment->latestFiles($registration)->except($code);
            $issues = $this->enrollment->submissionChecklist($registration, $files)[1]['documents'];
            $this->assertContains($code, array_column($issues, 'code'));
        }
        $this->expectException(ValidationException::class);
        $this->enrollment->validateStep(1, [...$this->answers(1), 'ceo_email' => 'bukan-email']);
    }

    public function test_failed_master_import_rolls_back_profile_documents_and_new_file_copies(): void
    {
        [$registration] = $this->invite();
        $registration = $this->fill($registration);
        $this->enrollment->saveStep($registration, $registration->version, 5, $this->answers(5), true);
        $paths = Storage::disk('scm')->allFiles();
        $calls = 0;
        $workflow = \Mockery::mock(Workflow::class)->makePartial();
        $workflow->shouldReceive('saveDocument')->andReturnUsing(function ($actor, $data, $file) use (&$calls) {
            if (++$calls === 2) {
                throw ValidationException::withMessages(['file' => 'DEMO kegagalan impor dokumen kedua.']);
            }

            return (new Workflow)->saveDocument($actor, $data, $file);
        });
        app()->instance(Workflow::class, $workflow);
        $this->actingAs($this->scm)->post(route('scm.vendor-decide', $registration),
            ['version' => $registration->fresh()->version, 'action' => 'accept'])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('scm_vendor_documents', 0);
        $this->assertSame('Vendor DEMO sebelum form', $this->vendor->fresh()->name);
        $this->assertSame('submitted', $registration->fresh()->status);
        $this->assertSame($paths, Storage::disk('scm')->allFiles());
    }

    public function test_submitted_forms_are_discoverable_without_opening_each_vendor_profile(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        [$registration] = $this->invite();
        $registration = $this->fill($registration);
        $this->enrollment->saveStep($registration, $registration->version, 5, $this->answers(5), true);
        $page = Livewire::actingAs($this->scm)->test(ScmProcurement::class)->call('switchSection', 'vendors')
            ->assertSet('vendorId', 0)->assertSee('Kiriman Form 005 menunggu pemeriksaan (1)')
            ->assertSee(route('scm.vendor-review', $registration));
        $this->enrollment->review($this->scm, $registration->id, $registration->fresh()->version, 'correction', 'Perbaiki data perusahaan dan lampiran.');
        $page->call('$refresh')->assertSee('Kiriman Form 005 menunggu pemeriksaan (0)');
    }

    public function test_continue_saves_answers_before_navigating_and_keeps_invalid_answers_on_current_page(): void
    {
        [$registration, $token] = $this->invite();
        $this->post(route('vendor.portal.save', ['token' => $token, 'step' => 1]),
            ['version' => $registration->fresh()->version, 'intent' => 'continue', 'answers' => $this->answers(1)])
            ->assertRedirect(route('vendor.portal', ['token' => $token, 'step' => 2]))->assertSessionHasNoErrors();
        $this->assertSame($this->answers(1)['name'], $registration->fresh()->answers[1]['name']);
        $this->post(route('vendor.portal.save', ['token' => $token, 'step' => 2]),
            ['version' => $registration->fresh()->version, 'intent' => 'continue', 'answers' => ['quality_certified' => '']])
            ->assertRedirect(route('vendor.portal', ['token' => $token, 'step' => 2]))->assertSessionHasErrors();
        $this->assertArrayNotHasKey(2, $registration->fresh()->answers);
    }

    public function test_last_page_lists_all_missing_answers_and_documents_with_scoped_links(): void
    {
        [$registration, $token] = $this->invite();
        $response = $this->get(route('vendor.portal', ['token' => $token, 'step' => 5]))->assertOk()
            ->assertSee('Kelengkapan sebelum dikirim')->assertSee('Dokumen wajib belum diunggah: Salinan Akta Pendirian Perusahaan')
            ->assertSee('Dokumen wajib belum diunggah: Salinan NPWP')
            ->assertSee(route('vendor.portal', ['token' => $token, 'step' => 1]).'#answer-city')
            ->assertSee(route('vendor.portal', ['token' => $token, 'step' => 1]).'#document-AKTA_PENDIRIAN');
        $this->assertNotEmpty($response->viewData('checklist')[1]['answers']);
        $this->assertNotEmpty($response->viewData('checklist')[2]['answers']);
        $this->assertNotEmpty($response->viewData('checklist')[3]['answers']);
        $this->assertNotEmpty($response->viewData('checklist')[4]['answers']);
        $this->assertSame(1, $registration->fresh()->version);
        $this->assertNull($registration->fresh()->submitted_at);
    }

    public function test_checklist_allows_optional_and_expired_documents_but_blocks_missing_stored_files(): void
    {
        [$registration] = $this->invite();
        $registration = $this->fill($registration);
        $checklist = $this->enrollment->submissionChecklist($registration);
        foreach ($checklist as $step) {
            $this->assertSame([], $step['answers']);
            $this->assertSame([], $step['documents']);
        }
        $registration->answers = array_replace($registration->answers, [1 => [...$registration->answers[1], 'non_pkp' => 'no', 'has_amendments' => 'yes']]);
        $codes = array_column($this->enrollment->submissionChecklist($registration)[1]['documents'], 'code');
        $this->assertNotContains('PKP', $codes);
        $this->assertNotContains('AKTA_PERUBAHAN', $codes);
        $file = $registration->files()->where('code', 'IZIN_USAHA')->sole();
        $file->update(['expires_at' => today()->subDay()]);
        $issues = $this->enrollment->submissionChecklist($registration)[1]['documents'];
        $this->assertSame([], $issues);
        $this->assertContains('IZIN_USAHA', array_column($this->enrollment->submissionChecklist($registration)[1]['warnings'], 'code'));
        $missingFile = $registration->files()->where('code', 'KTP')->sole();
        Storage::disk('scm')->delete($missingFile->path);
        $issues = $this->enrollment->submissionChecklist($registration)[1]['documents'];
        $this->assertContains('KTP', array_column($issues, 'code'));
        $registration->answers = array_replace($registration->answers, [5 => $this->answers(5)]);
        try {
            $this->enrollment->validateSubmission($registration);
            $this->fail('Submission must reject the same document issues shown in the checklist.');
        } catch (ValidationException $exception) {
            $this->assertSame($issues[0]['message'], $exception->errors()['documents'][0]);
        }
    }

    public function test_expired_invitation_review_does_not_offer_unavailable_decisions(): void
    {
        [$registration, $token] = $this->invite();
        $registration = $this->fill($registration);
        $this->enrollment->saveStep($registration, $registration->version, 5, $this->answers(5), true);
        $registration->fresh()->update(['expires_at' => now()->subMinute()]);
        $this->actingAs($this->scm)->get(route('scm.vendor-review', $registration))->assertOk()
            ->assertSee('Tautan kedaluwarsa')->assertSee('Undangan ini sudah kedaluwarsa')
            ->assertDontSee('Terima profil dan dokumen ke master')->assertDontSee('Minta perbaikan vendor')
            ->assertSee('Cabut tautan undangan');
        $this->post(route('scm.vendor-decide', $registration), ['version' => $registration->fresh()->version, 'action' => 'accept'])->assertGone();
        $this->assertSame('submitted', $registration->fresh()->status);
        $this->get(route('vendor.portal', ['token' => $token, 'step' => 5]))->assertGone();
    }
}
