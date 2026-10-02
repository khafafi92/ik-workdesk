<?php

namespace App\Services;

use App\Models\Department;
use App\Models\DocumentNumberingTemplate;
use App\Models\LetterProfile;
use App\Models\OutgoingLetter;
use App\Models\PermitCompany;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class HrKpmogLetterService
{
    public function nextNumber(?string $date = null): string
    {
        $template = $this->template();

        if ($template === null) {
            return '001';
        }

        $lastNumber = (int) ($template->sequences()
            ->where('year', Carbon::parse($date ?? today())->year)
            ->value('last_number') ?? 0);

        return str_pad((string) ($lastNumber + 1), 3, '0', STR_PAD_LEFT);
    }

    public function createDraft(User $user, ?string $date = null): OutgoingLetter
    {
        [$profile, $template] = $this->ensureConfiguration();

        $letter = OutgoingLetter::create([
            'letter_profile_id' => $profile->id,
            'permit_company_id' => $profile->permit_company_id,
            'department_id' => $profile->department_id,
            'document_date' => $date ?? today(),
            'subject' => 'Belum diisi',
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return app(DocumentNumberService::class)->reserve($letter);
    }

    private function template(): ?DocumentNumberingTemplate
    {
        return DocumentNumberingTemplate::query()
            ->whereHas('profile', fn ($query) => $query->where('code', 'HR-KPMOG'))
            ->where('is_active', true)
            ->first();
    }

    private function ensureConfiguration(): array
    {
        $company = PermitCompany::query()->where('code', 'KPMOG')->first();
        $department = Department::query()->where('code', 'HR')->first();

        if ($company === null || $department === null) {
            throw ValidationException::withMessages([
                'profile' => 'Entitas KPMOG atau departemen HR belum tersedia pada master data.',
            ]);
        }

        $profile = LetterProfile::query()->firstOrCreate(
            ['code' => 'HR-KPMOG'],
            [
                'name' => 'HR KPMOG',
                'permit_company_id' => $company->id,
                'department_id' => $department->id,
                'form_variant' => 'hr',
                'description' => 'Register nomor surat HR KPMOG.',
                'is_active' => true,
            ],
        );

        $template = DocumentNumberingTemplate::query()->firstOrCreate(
            [
                'letter_profile_id' => $profile->id,
                'permit_company_id' => $company->id,
                'department_id' => $department->id,
                'name' => 'Nomor urut HR KPMOG',
            ],
            [
                'template' => '{running:3}',
                'running_digits' => 3,
                'reset_period' => 'yearly',
                'priority' => 100,
                'is_active' => true,
            ],
        );

        return [$profile, $template];
    }
}
