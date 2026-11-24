<?php

namespace Tests\Feature\Scm;

use App\Models\Scm\VendorDocument;
use App\Models\Scm\VendorRegistration;
use App\Models\User;
use App\Services\Scm\ApcaVendorChecklist;
use App\Services\Scm\VendorEnrollment;
use App\Services\Scm\VendorForm;
use App\Services\Scm\VendorPublicPortal;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApcaVendorPortalTest extends TestCase
{
    use RefreshDatabase;

    private function company(): array
    {
        return ['name' => 'PT Vendor APCA QA', 'address' => 'Alamat uji', 'city' => 'Kota Uji', 'province' => 'Provinsi Uji',
            'postcode' => '12345', 'phone' => '0812345678', 'email' => 'office@example.test', 'ceo_name' => 'Direktur QA',
            'pic_name' => 'PIC Vendor', 'pic_position' => 'Admin', 'pic_phone' => '0812345679', 'pic_email' => 'pic@example.test',
            'apca_kind' => 'barang', 'apca_scale' => 'kecil', 'has_amendments' => 'no', 'non_pkp' => 'yes',
            'is_producer' => 'no', 'imports_goods' => 'no'];
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('dokumen-apca.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n");
    }

    public function test_company_selection_routes_to_apca_profile_or_existing_kpmog_form(): void
    {
        $apca = $this->post(route('vendor.register'), ['company' => 'APCA', 'name' => 'PT APCA Vendor QA'])->assertRedirect();
        $profileUrl = $apca->headers->get('Location');
        $this->get($profileUrl)->assertOk()->assertSee('Registrasi Vendor APCA')
            ->assertSee('Lihat checklist dokumen')->assertSee('Jasa Konsultan')->assertSee('Jasa Kontraktor')
            ->assertSee('Skala usaha')->assertDontSee('Manajemen Mutu');

        $checklist = $this->get(preg_replace('~/1$~', '/2', $profileUrl))->assertOk()
            ->assertSee('8. Surat Pengukuhan Pengusaha Kena Pajak (PKP)')->assertSee('Menunggu profil usaha')
            ->assertDontSee('id="file-APCA_', false);
        preg_match_all('/<strong>(\d+)\. /', $checklist->getContent(), $points);
        $this->assertSame(range(1, 28), array_map('intval', $points[1]));

        $kpmog = $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->post(route('vendor.register'), ['company' => 'KPMOG', 'name' => 'PT KPMOG Vendor QA', 'provider_type' => 'barang'])->assertRedirect();
        $this->get($kpmog->headers->get('Location'))->assertOk()->assertSee('Form 005')->assertSee('Manajemen Mutu')
            ->assertDontSee('Checklist Dokumen APCA');
    }

    public function test_public_apca_vendor_can_retrieve_their_id_throughout_the_authorized_session(): void
    {
        $draft = $this->post(route('vendor.register'), ['company' => 'APCA', 'name' => 'PT APCA Vendor QA'])->assertRedirect();
        $code = VendorRegistration::sole()->portalAccess->access_code;
        $draftUrl = $draft->headers->get('Location');
        $field = 'id="issued-vendor-code" value="'.$code.'"';
        $this->get($draftUrl)->assertOk()->assertSee($field, false);
        $this->get(preg_replace('~/1$~', '/2', $draftUrl))->assertOk()->assertSee($field, false);
        $this->get($draftUrl)->assertOk()->assertSee($field, false);

        [, $otherToken, $otherCode] = app(VendorPublicPortal::class)->register(['company' => 'APCA', 'name' => 'PT APCA Vendor Lain QA']);
        $this->get(route('vendor.portal', ['token' => $otherToken, 'step' => 1]))->assertOk()
            ->assertDontSee($code)->assertDontSee($otherCode);

        session()->invalidate();
        $this->get($draftUrl)->assertOk()->assertDontSee($code);
        $this->post(route('vendor.resume'), ['access_code' => $code])->assertRedirect($draftUrl);
        $this->get($draftUrl)->assertOk()->assertSee($field, false);
    }

    public function test_matrix_distinguishes_the_six_apca_profiles_and_conditional_documents(): void
    {
        [$record, $token] = app(VendorPublicPortal::class)->register(['company' => 'APCA', 'name' => 'PT Vendor APCA QA']);
        $portal = app(VendorEnrollment::class);
        $company = [...$this->company(), 'construction_consultancy' => 'yes', 'high_risk_work' => 'yes'];
        foreach ([
            ['barang', 'kecil', null, false, null, null],
            ['barang', 'besar', null, true, null, null],
            ['konsultan', 'kecil', false, true, null, null],
            ['konsultan', 'besar', true, true, false, null],
            ['kontraktor', 'kecil', true, true, true, true],
            ['kontraktor', 'besar', true, true, true, true],
        ] as [$kind, $scale, $sbu, $experience, $equipment, $k3]) {
            $answers = [1 => [...$company, 'apca_kind' => $kind, 'apca_scale' => $scale]];
            $requirements = ApcaVendorChecklist::requirements($answers);
            $this->assertSame($sbu, $requirements['APCA_SBU'] ?? null);
            $this->assertSame($experience, $requirements['APCA_PENGALAMAN']);
            $this->assertSame($equipment, $requirements['APCA_PERALATAN'] ?? null);
            $this->assertSame($k3, $requirements['APCA_K3'] ?? null);
            $this->assertSame($kind === 'barang' ? true : null, $requirements['APCA_KATALOG'] ?? null);
            $this->assertSame($kind === 'barang' ? false : null, $requirements['APCA_SERTIFIKAT_PRODUK'] ?? null);
            $this->assertSame($scale === 'besar', $requirements['APCA_NON_PKP']);
            $this->assertArrayNotHasKey('APCA_PKP', $requirements);
            $this->assertStringContainsString($scale === 'kecil' ? '3 bulan' : '6 bulan', ApcaVendorChecklist::documents($answers)['APCA_REKENING_KORAN'][4]);

            $portal->saveStep($record, $record->version, 1, $answers[1]);
            $record->refresh();
            $checklist = $this->get(route('vendor.portal', ['token' => $token, 'step' => 2]))->assertOk()
                ->assertDontSee('Tidak berlaku')->assertSee('Wajib')->assertSee('Opsional');
            preg_match_all('/id="document-(APCA_[A-Z0-9_]+)"/', $checklist->getContent(), $documents);
            $this->assertSame(array_keys($requirements), $documents[1]);
        }
        $conditional = ApcaVendorChecklist::requirements([1 => [...$company, 'has_amendments' => 'yes',
            'non_pkp' => 'no', 'is_producer' => 'yes', 'imports_goods' => 'yes']]);
        $this->assertTrue($conditional['APCA_AKTA_PERUBAHAN']);
        $this->assertFalse($conditional['APCA_PKP']);
        $this->assertFalse($conditional['APCA_SPT_PPN']);
        $this->assertFalse($conditional['APCA_IZIN_IMPOR']);
        $this->assertArrayNotHasKey('APCA_KEAGENAN', $conditional);
        $this->assertArrayNotHasKey('APCA_NON_PKP', $conditional);
    }

    public function test_submission_requires_apca_documents_and_scm_can_accept_them_into_master(): void
    {
        Storage::fake('scm');
        [$record, $token, $accessCode] = app(VendorPublicPortal::class)->register(['company' => 'APCA', 'name' => 'PT Vendor APCA QA']);
        $portal = app(VendorEnrollment::class);
        $this->post(route('vendor.portal.save', ['token' => $token, 'step' => 1]), [
            'version' => $record->version, 'intent' => 'continue',
            'answers' => [...$this->company(), 'company_origin' => 'foreign', 'document_basis' => 'npwp_ktp'],
        ])->assertRedirect(route('vendor.portal', ['token' => $token, 'step' => 2]));
        $record->refresh();
        $this->assertSame('company', $record->answers[1]['document_basis']);
        $this->get(route('vendor.portal', ['token' => $token, 'step' => 2]))->assertOk()
            ->assertSee('Barang - Kecil')
            ->assertSee('rekening koran 3 bulan terakhir.')
            ->assertSeeInOrder(['8. Surat Pernyataan Non-PKP', '28. Perjanjian kerahasiaan (NDA)'])
            ->assertDontSee('Sertifikat Badan Usaha (SBU)')->assertDontSee('E. K3 & Ketenagakerjaan')
            ->assertDontSee('8. Surat Pengukuhan Pengusaha Kena Pajak (PKP)')
            ->assertSee('id="file-APCA_NON_PKP"', false)->assertDontSee('id="file-APCA_SBU"', false);

        $uploadUrl = fn (string $code) => route('vendor.portal.upload', ['token' => $token, 'code' => $code]);
        $this->post($uploadUrl('KTP'), ['version' => $record->version, 'file' => $this->pdf()])->assertNotFound();
        $this->post($uploadUrl('APCA_K3'), ['version' => $record->version, 'file' => $this->pdf()])->assertSessionHasErrors('file');
        $declaration = ['full_name' => 'Direktur QA', 'position' => 'Direktur', 'date' => today()->toDateString(), 'confirmed' => '1'];
        $submit = route('vendor.portal.save', ['token' => $token, 'step' => 5]);
        $this->post($submit, ['version' => $record->version, 'answers' => $declaration, 'intent' => 'submit'])->assertSessionHasErrors('documents');

        foreach (VendorForm::requestedDocuments($record->answers, $record->context) as $code => $definition) {
            if (VendorForm::required($code, $record->answers, $record->context)) {
                $portal->upload($record, $record->fresh()->version, $code, ['notes' => 'Catatan vendor APCA'], $this->pdf());
            }
        }
        $this->post($submit, ['version' => $record->fresh()->version, 'answers' => $declaration, 'intent' => 'submit'])->assertSessionHasNoErrors();
        $this->assertSame('submitted', $record->fresh()->status);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('scm.vendor-review', $record))->assertOk()
            ->assertSee('Pemeriksaan Registrasi Vendor APCA')->assertSee('Catatan vendor APCA');
        $portal->review($admin, $record->id, $record->fresh()->version, 'accept', null);
        $this->assertSame('accepted', $record->fresh()->status);
        $this->assertTrue(VendorDocument::where('vendor_id', $record->fresh()->vendor_id)->where('notes', 'like', '%Catatan vendor APCA%')->exists());
        [$update] = app(VendorPublicPortal::class)->resume($accessCode);
        $this->assertTrue(ApcaVendorChecklist::applies($update->context));
        $this->assertSame('Catatan vendor APCA', $update->files()->first()->notes);
    }

    public function test_apca_cannot_submit_a_required_expired_sbu(): void
    {
        Storage::fake('scm');
        [$record, $token] = app(VendorPublicPortal::class)->register(['company' => 'APCA', 'name' => 'PT Konsultan QA']);
        $portal = app(VendorEnrollment::class);
        $portal->saveStep($record, $record->version, 1, [...$this->company(), 'apca_kind' => 'konsultan',
            'apca_scale' => 'besar', 'construction_consultancy' => 'yes']);
        $record->refresh();
        $this->post(route('vendor.portal.upload', ['token' => $token, 'code' => 'APCA_SBU']), [
            'version' => $record->version, 'file' => $this->pdf(),
        ])->assertSessionHasErrors('expires_at');
        foreach (ApcaVendorChecklist::requirements($record->answers) as $code => $required) {
            if ($required) {
                $metadata = $code === 'APCA_SBU' ? ['expires_at' => today()->subDay()->toDateString()] : [];
                $portal->upload($record, $record->fresh()->version, $code, $metadata, $this->pdf());
            }
        }
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Lampirkan SBU yang masih berlaku.');
        $portal->saveStep($record, $record->fresh()->version, 5,
            ['full_name' => 'Direktur QA', 'position' => 'Direktur', 'date' => today()->toDateString(), 'confirmed' => '1'], true);
    }

    public function test_apca_small_consultant_can_skip_optional_pkp_and_upload_optional_sbu_without_expiry(): void
    {
        Storage::fake('scm');
        [$record, $token] = app(VendorPublicPortal::class)->register(['company' => 'APCA', 'name' => 'PT Konsultan Kecil QA']);
        $portal = app(VendorEnrollment::class);
        $portal->saveStep($record, $record->version, 1, [...$this->company(), 'apca_kind' => 'konsultan',
            'construction_consultancy' => 'yes', 'non_pkp' => 'no']);
        $record->refresh();

        $checklist = $this->get(route('vendor.portal', ['token' => $token, 'step' => 2]))->assertOk();
        $document = new DOMDocument();
        $document->loadHTML($checklist->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        foreach (['APCA_SBU', 'APCA_PKP'] as $code) {
            $this->assertSame('Opsional · Belum diunggah', $xpath->evaluate('string(//*[@id="document-'.$code.'"]/summary//span[@class="vp-upload-state"])'));
        }
        $this->assertFalse($document->getElementById('expiry-APCA_SBU')->hasAttribute('required'));
        $missingDocuments = array_column($portal->submissionChecklist($record)[2]['documents'], 'code');
        $this->assertNotContains('APCA_SBU', $missingDocuments);
        $this->assertNotContains('APCA_PKP', $missingDocuments);

        $this->post(route('vendor.portal.upload', ['token' => $token, 'code' => 'APCA_SBU']), [
            'version' => $record->version, 'file' => $this->pdf(),
        ])->assertSessionHasNoErrors();
        $this->assertNull($record->files()->where('code', 'APCA_SBU')->firstOrFail()->expires_at);

        foreach (ApcaVendorChecklist::requirements($record->answers) as $code => $required) {
            if ($required) {
                $portal->upload($record, $record->fresh()->version, $code, [], $this->pdf());
            }
        }

        $this->post(route('vendor.portal.save', ['token' => $token, 'step' => 5]), [
            'version' => $record->fresh()->version, 'intent' => 'submit',
            'answers' => ['full_name' => 'Direktur QA', 'position' => 'Direktur', 'date' => today()->toDateString(), 'confirmed' => '1'],
        ])->assertSessionHasNoErrors();
        $this->assertSame('submitted', $record->fresh()->status);
        $this->assertFalse($record->files()->where('code', 'APCA_PKP')->exists());
    }
}
