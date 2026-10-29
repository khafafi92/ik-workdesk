<?php

namespace Tests\Feature\Scm;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Scm\Category;
use App\Models\Scm\Cbe;
use App\Models\Scm\DocumentType;
use App\Models\Scm\Procurement;
use App\Models\Scm\Requirement;
use App\Models\Scm\Vendor;
use App\Models\Scm\VendorDocument;
use App\Models\Scm\VendorRegistration;
use App\Models\User;
use App\Services\Scm\VendorEnrollment;
use App\Services\Scm\VendorForm;
use App\Services\Scm\Workflow;
use Database\Seeders\ScmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VendorDocumentProfilesTest extends TestCase
{
    use RefreshDatabase;

    private User $scm;

    private Vendor $vendor;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Storage::fake('scm');
        $this->seed(ScmSeeder::class);
        $this->scm = User::factory()->create(['is_admin' => false]);
        $this->department = Department::create(['code' => 'PROFILE-QA', 'name' => 'SCM QA']);
        Employee::create(['user_id' => $this->scm->id, 'name' => $this->scm->name, 'department_id' => $this->department->id, 'is_active' => true]);
        $this->scm->directPermissions()->attach(Permission::where('code', 'scm.process')->sole());
        $this->vendor = app(Workflow::class)->saveVendor($this->scm, ['code' => 'PROFILE-QA', 'name' => 'Vendor QA', 'provider_type' => 'keduanya', 'is_active' => true]);
    }

    private function invite(): array
    {
        [$registration, $url] = app(VendorEnrollment::class)->invite($this->scm, $this->vendor->id,
            ['company' => 'KPMOG', 'is_construction' => false, 'amount' => '', 'duration_months' => '']);
        preg_match('~/form-005/([a-f0-9]{64})/~', $url, $matches);

        return [$registration->fresh(), $matches[1]];
    }

    private function answers(int $step, string $origin = 'indonesia', string $basis = 'company'): array
    {
        $answers = [];
        foreach (VendorForm::fields($step, [1 => ['company_origin' => $origin, 'document_basis' => $basis]]) as $key => $field) {
            $answers[$key] = match ($field[1]) {
                'choice' => 'no', 'confirm' => '1', 'email' => 'qa@example.test', 'url' => '',
                'date' => today()->toDateString(), default => 'QA '.$key,
            };
        }
        if ($step === 1) {
            $answers += ['company_origin' => $origin, 'document_basis' => $basis];
            $answers['country'] = $origin === 'foreign' ? 'Singapore' : 'Indonesia';
            $answers['non_pkp'] = 'yes';
        }

        return $answers;
    }

    private function upload(VendorRegistration $registration, string $code): void
    {
        $definition = VendorForm::documents()[$code];
        app(VendorEnrollment::class)->upload($registration->fresh(), $registration->fresh()->version, $code,
            $definition[3] ? ['expires_at' => today()->addYear()->toDateString()] : [],
            UploadedFile::fake()->createWithContent('QA-dokumen.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n"));
    }

    private function completeForm(VendorRegistration $registration, string $origin, string $basis): VendorRegistration
    {
        $service = app(VendorEnrollment::class);
        foreach (range(1, 4) as $step) {
            $service->saveStep($registration->fresh(), $registration->fresh()->version, $step, $this->answers($step, $origin, $basis));
        }
        foreach (VendorForm::requestedDocuments($registration->fresh()->answers, $registration->context) as $code => $definition) {
            $this->upload($registration, $code);
        }
        $service->saveStep($registration->fresh(), $registration->fresh()->version, 5, $this->answers(5), true);
        $service->review($this->scm, $registration->id, $registration->fresh()->version, 'accept', null);

        return $registration->fresh();
    }

    public static function profiles(): array
    {
        return [['indonesia', 'company', 10], ['foreign', 'company', 7], ['indonesia', 'npwp_ktp', 2]];
    }

    #[DataProvider('profiles')]
    public function test_profile_submits_and_imports_only_its_own_documents(string $origin, string $basis, int $count): void
    {
        [$registration, $token] = $this->invite();
        $registration = $this->completeForm($registration, $origin, $basis);
        $this->assertSame('accepted', $registration->status);
        $this->assertSame($origin, VendorForm::origin($registration->answers));
        $this->assertCount($count, $registration->submitted_snapshot['files']);
        $this->assertSame($count, VendorDocument::where('vendor_id', $this->vendor->id)->count());
        $this->get(route('vendor.portal', ['token' => $token, 'step' => 1]))->assertOk()->assertDontSee('data-vp-form', false);
        if ($basis === 'npwp_ktp') {
            $this->assertEqualsCanonicalizing(['NPWP', 'KTP'], array_column($registration->submitted_snapshot['files'], 'code'));
        }
        if ($origin === 'foreign') {
            $this->assertFalse(VendorForm::required('NPWP', $registration->answers, $registration->context));
            $this->assertFalse(VendorForm::required('KTP', $registration->answers, $registration->context));
        }
    }

    public function test_foreign_profile_uses_exact_legal_quality_and_hse_sets_only_when_applicable(): void
    {
        $answers = [1 => ['company_origin' => 'foreign', 'country' => 'Singapore'], 2 => ['quality_system' => 'yes', 'quality_certified' => 'yes'],
            3 => ['ohs_system' => 'yes', 'drug_policy' => 'yes', 'risk_assessment' => 'yes', 'equipment_used' => 'yes', 'operator_licensed' => 'yes'],
            4 => ['blasting_used' => 'yes', 'vessel_loading' => 'yes']];
        $documents = VendorForm::requestedDocuments($answers, []);
        $this->assertCount(19, $documents);
        foreach (['FOREIGN_DEED', 'FOREIGN_LICENSE', 'FOREIGN_REGISTRY', 'FOREIGN_DOMICILE', 'MANUAL_MUTU', 'SERTIFIKAT_MUTU',
            'HSE_POLICY', 'MANUAL_K3', 'KEBIJAKAN_ALKOHOL', 'PENILAIAN_RISIKO', 'LIFTING_HOISTING', 'DAILY_INSPECTION',
            'EQUIPMENT_INSPECTION', 'LISENSI_OPERATOR', 'INTERNAL_BLASTING', 'VESSEL_LOADING'] as $code) {
            $this->assertArrayHasKey($code, $documents);
        }
        $this->assertArrayNotHasKey('AKTA_PENDIRIAN', $documents);
        $this->assertSame('ISO 9001: 2008 Certificate', VendorForm::documentLabel('SERTIFIKAT_MUTU', $answers));
        $this->assertSame('HSE Manual', VendorForm::documentLabel('MANUAL_K3', $answers));
        $answers[3]['equipment_used'] = 'no';
        $answers[4]['blasting_used'] = 'no';
        $this->assertFalse(VendorForm::required('DAILY_INSPECTION', $answers, []));
        $this->assertFalse(VendorForm::required('INTERNAL_BLASTING', $answers, []));
    }

    public function test_country_and_replacement_basis_are_validated_on_server(): void
    {
        $service = app(VendorEnrollment::class);
        $input = $this->answers(1, 'foreign', 'npwp_ktp');
        try {
            $service->validateStep(1, $input);
            $this->fail('Foreign company cannot use NPWP/KTP replacement.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('document_basis', $exception->errors());
        }
        $input['document_basis'] = 'company';
        $input['country'] = 'Indonesia';
        $this->expectException(ValidationException::class);
        $service->validateStep(1, $input);
    }

    public function test_indonesia_is_default_and_alternative_does_not_require_company_only_answers(): void
    {
        [$registration, $token] = $this->invite();
        $this->get(route('vendor.portal', ['token' => $token, 'step' => 1]))->assertOk()->assertSee('Asal perusahaan')
            ->assertSee('id="document-AKTA_PENDIRIAN"', false)->assertDontSee('id="document-FOREIGN_DEED"', false);
        $input = $this->answers(1, 'indonesia', 'npwp_ktp');
        unset($input['has_amendments'], $input['non_pkp']);
        app(VendorEnrollment::class)->saveStep($registration, $registration->version, 1, $input);
        $this->get(route('vendor.portal', ['token' => $token, 'step' => 1]))->assertOk()->assertSee('id="document-NPWP"', false)
            ->assertSee('id="document-KTP"', false)->assertDontSee('id="document-AKTA_PENDIRIAN"', false)
            ->assertDontSee('id="document-COMPANY_PROFILE"', false);
        $this->get(route('vendor.portal', ['token' => $token, 'step' => 3]))->assertOk()->assertDontSee('id="document-MANUAL_K3"', false);
    }

    public function test_upload_cannot_bypass_country_scoping(): void
    {
        [$registration] = $this->invite();
        app(VendorEnrollment::class)->saveStep($registration, $registration->version, 1, $this->answers(1, 'foreign'));
        $this->expectException(ValidationException::class);
        $this->upload($registration, 'KTP');
    }

    public function test_old_country_files_are_preserved_but_do_not_block_or_import_into_replacement_profile(): void
    {
        [$registration] = $this->invite();
        $this->upload($registration, 'IZIN_USAHA');
        $original = $registration->files()->sole();
        $original->update(['expires_at' => today()->subDay()]);
        $registration = $this->completeForm($registration, 'indonesia', 'npwp_ktp');
        Storage::disk('scm')->assertExists($original->path);
        $this->assertCount(3, $registration->files);
        $this->assertDatabaseCount('scm_vendor_documents', 2);
        $this->assertSame('accepted', $registration->status);
    }

    #[DataProvider('profiles')]
    public function test_new_legal_checklist_uses_reviewed_profile_and_preserves_custom_requirements(string $origin, string $basis, int $count): void
    {
        [$registration] = $this->invite();
        $registration = $this->completeForm($registration, $origin, $basis);
        $category = Category::create(['name' => 'Kategori QA']);
        $custom = DocumentType::create(['code' => 'CUSTOM-QA', 'name' => 'Dokumen khusus pengadaan', 'is_active' => true]);
        foreach ([DocumentType::where('code', 'NIB')->sole(), $custom] as $type) {
            Requirement::create(['category_id' => $category->id, 'document_type_id' => $type->id, 'is_required' => true]);
        }
        if ($origin === 'indonesia' && $basis === 'company') {
            $companyProfile = DocumentType::where('code', 'COMPANY_PROFILE')->sole();
            Requirement::create(['category_id' => $category->id, 'document_type_id' => $companyProfile->id, 'is_required' => true]);
        }
        $request = Procurement::create(['number' => 'QA-PROFILE-PR', 'kind' => 'PR', 'company' => 'KPMOG', 'need_type' => 'PROJECT',
            'date' => today(), 'requester_id' => $this->scm->id, 'department_id' => $this->department->id,
            'category_id' => $category->id, 'title' => 'QA', 'reason' => 'QA', 'status' => 'selected']);
        Cbe::create(['request_id' => $request->id, 'number' => 'QA-PROFILE-CBE', 'date' => today(), 'status' => 'final', 'selected_vendor_id' => $this->vendor->id, 'prepared_by' => $this->scm->id]);
        $request = app(Workflow::class)->startLegal($this->scm, $request->id, $request->fresh()->version);
        $case = $request->legalCase;
        $this->assertSame($registration->id, $case->document_basis_snapshot['registration_id']);
        $this->assertTrue($case->items()->where('document_type_id', $custom->id)->sole()->is_required);
        if (isset($companyProfile)) {
            $this->assertTrue($case->items()->where('document_type_id', $companyProfile->id)->sole()->is_required);
        }
        $nib = $case->items()->where('document_type_id', DocumentType::where('code', 'NIB')->value('id'))->sole();
        $this->assertSame($origin === 'indonesia' && $basis === 'company', $nib->is_required);
        $this->assertSame($count + 2, $case->items()->count());
        $before = $case->items()->orderBy('id')->get()->toArray();
        $registration->update(['answers' => [1 => ['company_origin' => 'indonesia', 'document_basis' => 'npwp_ktp']]]);
        $this->assertSame($before, $case->items()->orderBy('id')->get()->toArray());
        if ($origin === 'foreign') {
            $this->expectException(ValidationException::class);
            app(Workflow::class)->useAlternativeDocuments($this->scm, $request->id, $request->version, 'Tidak boleh mengubah perusahaan asing menjadi pengganti Indonesia.');
        }
    }
}
