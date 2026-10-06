<?php

namespace Tests\Feature;

use App\Filament\Resources\AteGeneralLetters\AteGeneralLetterResource;
use App\Filament\Resources\AteGeneralLetters\Pages\EditAteGeneralLetter;
use App\Filament\Resources\HrApcaLetters\HrApcaLetterResource;
use App\Filament\Resources\HrApcaLetters\Pages\EditHrApcaLetter;
use App\Filament\Resources\HrKpmogLetters\HrKpmogLetterResource;
use App\Filament\Resources\HrKpmogLetters\Pages\EditHrKpmogLetter;
use App\Filament\Resources\KpmogProjectLetters\KpmogProjectLetterResource;
use App\Filament\Resources\KpmogProjectLetters\Pages\EditKpmogProjectLetter;
use App\Filament\Resources\OutgoingLetters\Pages\CreateOutgoingLetter;
use App\Models\Department;
use App\Models\DocumentNumberingTemplate;
use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\LetterProfile;
use App\Models\OutgoingLetter;
use App\Models\PermitCompany;
use App\Models\User;
use App\Services\AteGeneralLetterService;
use App\Services\DocumentNumberService;
use App\Services\HrApcaLetterService;
use App\Services\HrKpmogLetterService;
use App\Services\KpmogProjectLetterService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
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

    public function test_apca_outgoing_letter_uses_the_creators_department_and_requested_number_format(): void
    {
        $company = $this->company('APCA');
        $creatorDepartment = Department::create(['code' => 'IT', 'name' => 'Information Technology', 'is_active' => true]);
        $otherDepartment = Department::create(['code' => 'HR', 'name' => 'Human Resources', 'is_active' => true]);
        $type = $this->type('TUGAS');
        $profile = LetterProfile::create([
            'code' => 'ATE-UMUM',
            'name' => 'Surat Umum APCA',
            'permit_company_id' => $company->id,
            'form_variant' => 'general',
            'is_active' => true,
        ]);
        DocumentNumberingTemplate::create([
            'letter_profile_id' => $profile->id,
            'permit_company_id' => $company->id,
            'document_type_id' => $type->id,
            'name' => 'Nomor surat APCA',
            'template' => '{running:3}/{department_code}-ATE/{roman_month}/{year}',
            'reset_period' => 'yearly',
            'is_active' => true,
        ]);
        $creator = $this->userWithDepartment('IT', ['is_admin' => true]);
        $this->actingAs($creator);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateOutgoingLetter::class)
            ->fillForm([
                'letter_profile_id' => $profile->id,
                'permit_company_id' => $company->id,
                'department_id' => $otherDepartment->id,
                'document_type_id' => $type->id,
                'document_date' => '2026-10-15',
                'subject' => 'Surat tugas',
                'is_legacy_number' => false,
            ])
            ->assertSeeText('001/IT-ATE/X/2026')
            ->call('create')
            ->assertHasNoFormErrors();

        $letter = OutgoingLetter::query()->latest('id')->firstOrFail();
        $this->assertSame($creatorDepartment->id, $letter->department_id);

        app(DocumentNumberService::class)->issue($letter, $creator);

        $this->assertSame('001/IT-ATE/X/2026', $letter->refresh()->document_number);
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

    public function test_hr_kpmog_draft_reserves_the_next_number_before_the_letter_is_issued(): void
    {
        $this->company('KPMOG');
        Department::create(['code' => 'HR', 'name' => 'Human Resources', 'is_active' => true]);
        $issuer = User::factory()->create();
        $service = app(HrKpmogLetterService::class);

        $first = $service->createDraft($issuer, '2026-10-02');

        $this->assertSame('001/KPMOG-HR/X/2026', $first->document_number);
        $this->assertSame('draft', $first->status);
        $this->assertSame('HR-KPMOG', $first->profile->code);

        $first->update(['subject' => 'Surat Pengantar MCU']);
        app(DocumentNumberService::class)->issue($first, $issuer);

        $this->assertSame('001/KPMOG-HR/X/2026', $first->refresh()->document_number);
        $this->assertSame('issued', $first->status);
        $this->assertSame('002', $service->nextNumber());
    }

    public function test_hr_kpmog_draft_uses_the_selected_letter_date_for_its_yearly_sequence(): void
    {
        $this->company('KPMOG');
        Department::create(['code' => 'HR', 'name' => 'Human Resources', 'is_active' => true]);
        $issuer = User::factory()->create();
        $service = app(HrKpmogLetterService::class);

        $first = $service->createDraft($issuer, '2025-12-31');
        $second = $service->createDraft($issuer, '2026-01-01');

        $this->assertSame('2025-12-31', $first->document_date->toDateString());
        $this->assertSame('001/KPMOG-HR/XII/2025', $first->document_number);
        $this->assertSame('001/KPMOG-HR/I/2026', $second->document_number);
        $this->assertSame('002', $service->nextNumber('2026-10-02'));
    }

    public function test_hr_apca_draft_uses_one_running_sequence_and_formats_the_selected_letter_type(): void
    {
        $this->company('KPMOG');
        $this->company('APCA');
        Department::create(['code' => 'HR', 'name' => 'Human Resources', 'is_active' => true]);
        Department::create(['code' => 'HC', 'name' => 'Human Capital', 'is_active' => true]);
        $issuer = User::factory()->create();
        app(HrKpmogLetterService::class)->createDraft($issuer);
        $service = app(HrApcaLetterService::class);

        $first = $service->createDraft($issuer, '2024-09-30', 'SKK');
        $second = $service->createDraft($issuer, '2025-10-01', 'SKET');

        $this->assertSame('001/ATE-HC/SKK/IX/2024', $first->document_number);
        $this->assertSame('002/ATE-HR/SKET/X/2025', $second->document_number);
        $this->assertSame('003', $service->nextNumber());
    }

    public function test_hr_apca_draft_requires_a_letter_type_and_reserves_a_complete_number(): void
    {
        $this->company('KPMOG');
        $this->company('APCA');
        Department::create(['code' => 'HR', 'name' => 'Human Resources', 'is_active' => true]);
        Department::create(['code' => 'HC', 'name' => 'Human Capital', 'is_active' => true]);
        $issuer = User::factory()->create();

        app(HrKpmogLetterService::class)->createDraft($issuer);
        $letter = app(HrApcaLetterService::class)->createDraft($issuer, '2025-09-18', 'SKK');

        $this->assertSame('001/ATE-HC/SKK/IX/2025', $letter->document_number);
        $this->assertNotNull($letter->document_type_id);
        $this->assertSame('draft', $letter->status);
    }

    public function test_saving_hr_drafts_returns_to_their_respective_register(): void
    {
        $this->company('KPMOG');
        $this->company('APCA');
        Department::create(['code' => 'HR', 'name' => 'Human Resources', 'is_active' => true]);
        Department::create(['code' => 'HC', 'name' => 'Human Capital', 'is_active' => true]);
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $kpmogLetter = app(HrKpmogLetterService::class)->createDraft($user);
        $apcaLetter = app(HrApcaLetterService::class)->createDraft($user, '2025-09-18', 'SKK');

        Livewire::test(EditHrKpmogLetter::class, ['record' => $kpmogLetter->id])
            ->fillForm(['subject' => 'Surat Pengantar MCU'])
            ->call('save')
            ->assertRedirect(HrKpmogLetterResource::getUrl('index'));
        Livewire::test(EditHrApcaLetter::class, ['record' => $apcaLetter->id])
            ->fillForm(['subject' => 'Surat undangan'])
            ->call('save')
            ->assertRedirect(HrApcaLetterResource::getUrl('index'));
    }

    public function test_kpmog_project_and_bd_letters_share_one_yearly_number_sequence(): void
    {
        $this->company('KPMOG');
        $pm = Department::create(['code' => 'PM', 'name' => 'Project Management', 'is_active' => true]);
        $bd = Department::create(['code' => 'BD', 'name' => 'Business Development', 'is_active' => true]);
        $issuer = User::factory()->create();
        $service = app(KpmogProjectLetterService::class);

        $first = $service->createDraft($issuer, '2026-01-02', $pm->id);
        $second = $service->createDraft($issuer, '2026-02-19', $bd->id);

        $this->assertSame('001/KPMOG-PM/I/2026', $first->document_number);
        $this->assertSame('002/KPMOG-BD/II/2026', $second->document_number);
        $this->assertSame('003', $service->nextNumber('2026-03-01'));
    }

    public function test_saving_kpmog_project_draft_returns_to_its_register(): void
    {
        $this->company('KPMOG');
        $department = Department::create(['code' => 'PM', 'name' => 'Project Management', 'is_active' => true]);
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $letter = app(KpmogProjectLetterService::class)->createDraft($user, '2026-01-02', $department->id);

        Livewire::test(EditKpmogProjectLetter::class, ['record' => $letter->id])
            ->fillForm(['subject' => 'Pengadaan material proyek'])
            ->call('save')
            ->assertRedirect(KpmogProjectLetterResource::getUrl('index'));
    }

    public function test_ate_general_letters_use_their_respective_master_number_formats(): void
    {
        $this->company('APCA');
        $user = $this->userWithDepartment('IT');
        $service = app(AteGeneralLetterService::class);

        $perdin = $service->createDraft($user, '2024-04-10', 'perdin');
        $tugas = $service->createDraft($user, '2025-08-15', 'tugas');
        $ttd = $service->createDraft($user, '2025-07-20', 'tanda_terima_dok');
        $memo = $service->createDraft($user, '2026-02-01', 'info_memo_luwuk');
        $nextPerdin = $service->createDraft($user, '2024-05-01', 'perdin');

        $this->assertSame('001/IT-ATE/IV/2024', $perdin->document_number);
        $this->assertSame('001/IT-ATE/VIII/2025', $tugas->document_number);
        $this->assertSame('001/IT-ATE/VII/2025', $ttd->document_number);
        $this->assertSame('001/IT-ATE/II/2026', $memo->document_number);
        $this->assertSame('002/IT-ATE/V/2024', $nextPerdin->document_number);
    }

    public function test_ate_general_letter_choices_and_prefixes_follow_the_document_type_master(): void
    {
        $this->company('APCA');
        $user = $this->userWithDepartment('IT');
        $service = app(AteGeneralLetterService::class);

        $service->createDraft($user, '2024-04-10', 'perdin');

        DocumentType::query()->where('code', 'ATE-PERDIN')->update([
            'name' => 'Surat Perdin (A.01)',
        ]);
        DocumentType::query()->where('code', 'ATE-TUGAS')->update([
            'name' => 'Surat Tugas (B.01)',
        ]);
        DocumentType::query()->where('code', 'ATE-PERNYATAAN')->update([
            'name' => 'Surat Pernyataan (B.01)',
        ]);
        DocumentType::query()->where('code', 'ATE-PENGANTAR')->update([
            'name' => 'Surat Pengantar (B.01)',
        ]);
        DocumentType::query()->where('code', 'ATE-PERMOHONAN')->update([
            'name' => 'Surat Permohonan (B.01)',
        ]);
        DocumentType::query()->where('code', 'ATE-KEPUTUSAN')->update([
            'name' => 'Surat Keputusan (A.01)',
        ]);
        DocumentType::query()->where('code', 'ATE-SKET')->update([
            'name' => 'SKET (B.01)',
        ]);
        DocumentType::query()->where('code', 'ATE-TTD')->update([
            'name' => 'Tanda Terima Dokumen (B.01)',
        ]);
        DocumentType::query()->where('code', 'ATE-INFO-MEMO-LWK')->update([
            'name' => 'Info Memo Luwuk (FIN-LWK)',
        ]);

        $this->assertSame([
            'perdin' => 'Surat Perdin (A.01)',
            'tugas' => 'Surat Tugas (B.01)',
            'pernyataan' => 'Surat Pernyataan (B.01)',
            'pengantar' => 'Surat Pengantar (B.01)',
            'permohonan' => 'Surat Permohonan (B.01)',
            'keputusan' => 'Surat Keputusan (A.01)',
            'sket' => 'SKET (B.01)',
            'tanda_terima_dok' => 'Tanda Terima Dokumen (B.01)',
            'info_memo_luwuk' => 'Info Memo Luwuk (FIN-LWK)',
        ], $service->kinds());

        $perdin = $service->createDraft($user, '2025-04-10', 'perdin');
        $receipt = $service->createDraft($user, '2025-07-20', 'tanda_terima_dok');

        $this->assertSame('001/IT-ATE/IV/2025', $perdin->document_number);
        $this->assertSame('001/IT-ATE/VII/2025', $receipt->document_number);
    }

    public function test_ate_general_letters_require_the_creator_to_have_an_active_department(): void
    {
        $this->company('APCA');
        $user = User::factory()->create();

        try {
            app(AteGeneralLetterService::class)->createDraft($user, '2026-10-01', 'perdin');
            $this->fail('A user without a department must not receive a letter number.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('department_id', $exception->errors());
        }

        $this->assertDatabaseCount('outgoing_letters', 0);
    }

    public function test_ate_general_draft_keeps_its_creator_department_when_the_user_department_changes(): void
    {
        $this->company('APCA');
        $user = $this->userWithDepartment('IT');
        $letter = app(AteGeneralLetterService::class)->createDraft($user, '2026-10-01', 'perdin');
        $hr = Department::query()->create(['code' => 'HR', 'name' => 'Human Resources', 'is_active' => true]);

        $user->employee->update(['department_id' => $hr->id]);
        $updated = app(AteGeneralLetterService::class)->refreshNumber($letter);

        $this->assertSame('001/IT-ATE/X/2026', $updated->document_number);
        $this->assertSame($letter->department_id, $updated->department_id);
    }

    public function test_ate_general_draft_without_saved_department_uses_its_creator_department(): void
    {
        $this->company('APCA');
        $user = $this->userWithDepartment('IT');
        $letter = app(AteGeneralLetterService::class)->createDraft($user, '2026-10-01', 'perdin');
        $letter->update(['department_id' => null]);

        $updated = app(AteGeneralLetterService::class)->refreshNumber($letter->fresh());

        $this->assertSame('001/IT-ATE/X/2026', $updated->document_number);
        $this->assertSame($user->employee->department_id, $updated->department_id);
    }

    public function test_saving_ate_general_draft_returns_to_its_register(): void
    {
        $this->company('APCA');
        $user = $this->userWithDepartment('IT', ['is_admin' => true]);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $letter = app(AteGeneralLetterService::class)->createDraft($user, '2025-08-15', 'tugas');

        Livewire::test(EditAteGeneralLetter::class, ['record' => $letter->id])
            ->fillForm(['subject' => 'Surat tugas inspeksi'])
            ->call('save')
            ->assertRedirect(AteGeneralLetterResource::getUrl('index'));
    }

    public function test_administrator_can_render_all_surat_pages(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);

        foreach ([
            '/panel/outgoing-letters',
            '/panel/outgoing-letters/create',
            '/panel/hr-kpmog-letters',
            '/panel/hr-apca-letters',
            '/panel/kpmog-project-letters',
            '/panel/ate-general-letters',
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

    private function userWithDepartment(string $departmentCode, array $userAttributes = []): User
    {
        $department = Department::query()->firstOrCreate(
            ['code' => $departmentCode],
            ['name' => $departmentCode, 'is_active' => true],
        );
        $user = User::factory()->create($userAttributes);

        Employee::query()->create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'name' => $user->name,
            'is_active' => true,
        ]);

        return $user;
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
