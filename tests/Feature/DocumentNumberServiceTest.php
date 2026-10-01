<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DocumentNumberingTemplate;
use App\Models\DocumentType;
use App\Models\LetterProfile;
use App\Models\OutgoingLetter;
use App\Models\PermitCompany;
use App\Models\User;
use App\Services\DocumentNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DocumentNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_formatting_covers_the_required_numbering_examples(): void
    {
        $ate = $this->company('ATE');
        $kpmog = $this->company('KPMOG');
        $hc = Department::create(['code' => 'HC', 'name' => 'Human Capital', 'is_active' => true]);
        $pm = Department::create(['code' => 'PM', 'name' => 'Project Management', 'is_active' => true]);
        $fin = Department::create(['code' => 'FIN', 'name' => 'Finance', 'is_active' => true]);
        $service = app(DocumentNumberService::class);

        $this->assertSame('B.08/007/ATE/VIII/2025', $this->format(
            $service,
            $ate,
            null,
            $this->type('B.08'),
            '{document_code}/{running:3}/{company_code}/{roman_month}/{year}',
            '2025-08-15',
            7,
        ));
        $this->assertSame('001/ATE-HC/SKK/IX/2024', $this->format(
            $service,
            $ate,
            $hc,
            $this->type('SKK'),
            '{running:3}/{company_code}-{department_code}/{document_code}/{roman_month}/{year}',
            '2024-09-10',
            1,
        ));
        $this->assertSame('001/KPMOG-PM/I/2026', $this->format(
            $service,
            $kpmog,
            $pm,
            null,
            '{running:3}/{company_code}-{department_code}/{roman_month}/{year}',
            '2026-01-08',
            1,
        ));
        $this->assertSame('001-FIN-LWK-ATE-II-2026', $this->format(
            $service,
            $ate,
            $fin,
            $this->type('LWK'),
            '{running:3}-{department_code}-{document_code}-{company_code}-{roman_month}-{year}',
            '2026-02-01',
            1,
        ));
        $this->assertSame('B.014/001/ATE/VII/2025', $this->format(
            $service,
            $ate,
            null,
            $this->type('B.014'),
            '{document_code}/{running:3}/{company_code}/{roman_month}/{year}',
            '2025-07-20',
            1,
        ));
    }

    public function test_issued_letters_receive_a_unique_incrementing_number_without_touching_ticket_sequences(): void
    {
        $company = $this->company('ATE');
        $type = $this->type('B.08');
        $template = DocumentNumberingTemplate::create([
            'permit_company_id' => $company->id,
            'document_type_id' => $type->id,
            'name' => 'ATE B.08 tahunan',
            'template' => '{document_code}/{running:3}/{company_code}/{roman_month}/{year}',
            'running_digits' => 3,
            'reset_period' => 'yearly',
            'is_active' => true,
        ]);
        $issuer = User::factory()->create();
        $first = $this->letter($company, $type, '2025-08-15');
        $second = $this->letter($company, $type, '2025-10-15');
        $service = app(DocumentNumberService::class);

        $service->issue($first, $issuer);
        $service->issue($second, $issuer);

        $this->assertSame('B.08/001/ATE/VIII/2025', $first->refresh()->document_number);
        $this->assertSame('B.08/002/ATE/X/2025', $second->refresh()->document_number);
        $this->assertSame('issued', $second->status);
        $this->assertDatabaseHas('document_number_sequences', [
            'document_numbering_template_id' => $template->id,
            'last_number' => 2,
        ]);
        $this->assertDatabaseCount('document_sequences', 0);
    }

    public function test_legacy_number_is_preserved_and_does_not_reserve_a_new_sequence(): void
    {
        $company = $this->company('ATE');
        $letter = OutgoingLetter::create([
            'permit_company_id' => $company->id,
            'document_date' => '2025-08-15',
            'subject' => 'Surat lama',
            'document_number' => 'B.08/099/ATE/VIII/2025',
            'is_legacy_number' => true,
        ]);

        app(DocumentNumberService::class)->issue($letter, User::factory()->create());

        $this->assertSame('B.08/099/ATE/VIII/2025', $letter->refresh()->document_number);
        $this->assertSame('issued', $letter->status);
        $this->assertDatabaseCount('document_number_sequences', 0);
    }

    public function test_preview_uses_the_most_specific_template_without_reserving_a_number(): void
    {
        $company = $this->company('ATE');
        $department = Department::create(['code' => 'HC', 'name' => 'Human Capital', 'is_active' => true]);
        $type = $this->type('SKK');
        DocumentNumberingTemplate::create([
            'permit_company_id' => $company->id,
            'name' => 'Template umum ATE',
            'template' => 'GENERAL/{running:3}',
            'is_active' => true,
        ]);
        DocumentNumberingTemplate::create([
            'permit_company_id' => $company->id,
            'department_id' => $department->id,
            'document_type_id' => $type->id,
            'name' => 'Template HC SKK',
            'template' => '{running:3}/{company_code}-{department_code}/{document_code}/{roman_month}/{year}',
            'is_active' => true,
        ]);
        $letter = OutgoingLetter::make([
            'permit_company_id' => $company->id,
            'department_id' => $department->id,
            'document_type_id' => $type->id,
            'document_date' => '2024-09-10',
        ]);

        $this->assertSame('001/ATE-HC/SKK/IX/2024', app(DocumentNumberService::class)->preview($letter));
        $this->assertDatabaseCount('document_number_sequences', 0);
    }

    public function test_issued_letter_content_and_number_are_immutable(): void
    {
        $company = $this->company('ATE');
        $letter = OutgoingLetter::create([
            'permit_company_id' => $company->id,
            'document_date' => '2025-08-15',
            'subject' => 'Surat lama',
            'document_number' => 'B.08/099/ATE/VIII/2025',
            'is_legacy_number' => true,
        ]);
        app(DocumentNumberService::class)->issue($letter, User::factory()->create());

        $this->expectException(ValidationException::class);
        $letter->update(['subject' => 'Perihal yang tidak boleh berubah']);
    }

    public function test_profile_specific_template_is_used_for_the_selected_profile(): void
    {
        $company = $this->company('ATE');
        $hrProfile = LetterProfile::create([
            'code' => 'HR-APCA',
            'name' => 'HR APCA',
            'permit_company_id' => $company->id,
            'form_variant' => 'hr',
        ]);
        $generalProfile = LetterProfile::create([
            'code' => 'APCA-UMUM',
            'name' => 'Surat Umum APCA',
            'permit_company_id' => $company->id,
        ]);
        DocumentNumberingTemplate::create([
            'letter_profile_id' => $hrProfile->id,
            'permit_company_id' => $company->id,
            'name' => 'HR APCA',
            'template' => 'HR/{running:3}',
            'is_active' => true,
        ]);
        DocumentNumberingTemplate::create([
            'letter_profile_id' => $generalProfile->id,
            'permit_company_id' => $company->id,
            'name' => 'Surat umum APCA',
            'template' => 'UMUM/{running:3}',
            'is_active' => true,
        ]);

        $letter = OutgoingLetter::make([
            'letter_profile_id' => $hrProfile->id,
            'permit_company_id' => $company->id,
            'document_date' => '2026-10-01',
        ]);

        $this->assertSame('HR/001', app(DocumentNumberService::class)->preview($letter));
    }

    public function test_administrator_can_render_all_surat_pages(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);

        foreach ([
            '/panel/outgoing-letters',
            '/panel/outgoing-letters/create',
            '/panel/letter-profiles',
            '/panel/letter-profiles/create',
            '/panel/document-types',
            '/panel/document-types/create',
            '/panel/document-numbering-templates',
            '/panel/document-numbering-templates/create',
        ] as $url) {
            $this->actingAs($administrator)->get($url)->assertOk();
        }
    }

    private function type(string $code): DocumentType
    {
        return DocumentType::create(['name' => 'Jenis '.$code, 'code' => $code, 'is_active' => true]);
    }

    private function company(string $code): PermitCompany
    {
        return PermitCompany::firstOrCreate(
            ['code' => $code],
            ['name' => $code, 'is_active' => true],
        );
    }

    private function letter(PermitCompany $company, ?DocumentType $type, string $date): OutgoingLetter
    {
        return OutgoingLetter::create([
            'permit_company_id' => $company->id,
            'document_type_id' => $type?->id,
            'document_date' => $date,
            'subject' => 'Surat pengujian',
        ]);
    }

    private function format(
        DocumentNumberService $service,
        PermitCompany $company,
        ?Department $department,
        ?DocumentType $type,
        string $format,
        string $date,
        int $runningNumber,
    ): string {
        $template = new DocumentNumberingTemplate(['template' => $format, 'running_digits' => 3]);
        $letter = new OutgoingLetter([
            'permit_company_id' => $company->id,
            'department_id' => $department?->id,
            'document_type_id' => $type?->id,
            'document_date' => $date,
        ]);

        return $service->format($template, $letter, $runningNumber);
    }
}
