<?php

namespace App\Services;

use App\Models\Department;
use App\Models\DocumentNumberingTemplate;
use App\Models\LetterProfile;
use App\Models\OutgoingLetter;
use App\Models\PermitCompany;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KpmogProjectLetterService
{
    public function departmentOptions(): array
    {
        return Department::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Department $department): array => [$department->id => $department->code.' - '.$department->name])
            ->all();
    }

    public function nextNumber(?string $date = null): string
    {
        $template = $this->template();

        if ($template === null) {
            return '001';
        }

        $year = Carbon::parse($date ?? today())->year;
        $lastNumber = (int) DB::table('document_number_sequences')
            ->where('document_numbering_template_id', $template->id)
            ->where('year', $year)
            ->value('last_number');

        return str_pad((string) ($lastNumber + 1), 3, '0', STR_PAD_LEFT);
    }

    public function createDraft(User $user, string $date, int $departmentId): OutgoingLetter
    {
        [$profile] = $this->ensureConfiguration();
        $department = Department::query()->where('is_active', true)->find($departmentId);

        if ($department === null) {
            throw ValidationException::withMessages(['department_id' => 'Pilih departemen yang aktif.']);
        }

        $letter = OutgoingLetter::create([
            'letter_profile_id' => $profile->id,
            'permit_company_id' => $profile->permit_company_id,
            'department_id' => $department->id,
            'document_date' => $date,
            'subject' => 'Belum diisi',
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return app(DocumentNumberService::class)->reserve($letter);
    }

    /** @return array{0: LetterProfile, 1: DocumentNumberingTemplate} */
    public function configuration(): array
    {
        return $this->ensureConfiguration();
    }

    public function refreshNumber(OutgoingLetter $letter): OutgoingLetter
    {
        if ($letter->status !== 'draft' || blank($letter->running_number)) {
            return $letter;
        }

        $template = $letter->numberingTemplate;

        if ($template === null) {
            throw ValidationException::withMessages(['document_number' => 'Template nomor surat tidak ditemukan.']);
        }

        $letter->update([
            'document_number' => app(DocumentNumberService::class)->format($template, $letter, $letter->running_number),
            'updated_by' => auth()->id(),
        ]);

        return $letter->refresh();
    }

    private function template(): ?DocumentNumberingTemplate
    {
        return DocumentNumberingTemplate::query()
            ->whereHas('profile', fn ($query) => $query->where('code', 'KPMOG-PROJECT-BD'))
            ->where('name', 'Nomor surat KPMOG Project dan BD')
            ->where('is_active', true)
            ->first();
    }

    private function ensureConfiguration(): array
    {
        $company = PermitCompany::query()->where('code', 'KPMOG')->first();

        if ($company === null) {
            throw ValidationException::withMessages(['profile' => 'Entitas KPMOG belum tersedia pada master data.']);
        }

        $profile = LetterProfile::query()->firstOrCreate(
            ['code' => 'KPMOG-PROJECT-BD'],
            [
                'name' => 'KPMOG Project dan BD',
                'permit_company_id' => $company->id,
                'form_variant' => 'project',
                'description' => 'Register surat KPMOG Team Project dan Business Development.',
                'is_active' => true,
            ],
        );

        $template = DocumentNumberingTemplate::query()->firstOrCreate(
            [
                'letter_profile_id' => $profile->id,
                'permit_company_id' => $company->id,
                'name' => 'Nomor surat KPMOG Project dan BD',
            ],
            [
                'template' => '{running:3}/{company_code}-{department_code}/{roman_month}/{year}',
                'running_digits' => 3,
                'reset_period' => 'yearly',
                'priority' => 100,
                'is_active' => true,
            ],
        );

        return [$profile, $template];
    }
}
