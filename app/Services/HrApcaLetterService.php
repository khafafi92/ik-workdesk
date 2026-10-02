<?php

namespace App\Services;

use App\Models\Department;
use App\Models\DocumentNumberingTemplate;
use App\Models\DocumentType;
use App\Models\LetterProfile;
use App\Models\OutgoingLetter;
use App\Models\PermitCompany;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class HrApcaLetterService
{
    public function kinds(): array
    {
        return [
            'SKK' => 'SKK - Surat Keterangan Kerja',
            'SKET' => 'SKET - Surat Keterangan / Nonaktif BPJS',
        ];
    }

    public function typeOptions(): array
    {
        $this->ensureConfiguration();

        return DocumentType::query()
            ->whereIn('code', array_keys($this->kinds()))
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (DocumentType $type): array => [$type->id => $this->kinds()[$type->code] ?? $type->name])
            ->all();
    }

    public function nextNumber(): string
    {
        $template = $this->template();

        if ($template === null) {
            return '001';
        }

        $lastNumber = (int) ($template->sequences()->value('last_number') ?? 0);

        return str_pad((string) ($lastNumber + 1), 3, '0', STR_PAD_LEFT);
    }

    public function createDraft(User $user, string $date, ?string $kind = null): OutgoingLetter
    {
        [$profile] = $this->ensureConfiguration();
        $type = filled($kind) ? $this->typeFor($kind) : null;

        $letter = OutgoingLetter::create([
            'letter_profile_id' => $profile->id,
            'permit_company_id' => $profile->permit_company_id,
            'department_id' => $profile->department_id,
            'document_type_id' => $type?->id,
            'document_date' => $date,
            'subject' => 'Belum diisi',
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return app(DocumentNumberService::class)->reserve(
            $letter,
            fn (DocumentNumberingTemplate $template, OutgoingLetter $draft, int $runningNumber): string => filled($kind)
                ? $this->formatNumber($draft, $runningNumber)
                : $this->serialNumber($runningNumber),
        );
    }

    public function refreshNumber(OutgoingLetter $letter): OutgoingLetter
    {
        if ($letter->status !== 'draft' || blank($letter->running_number)) {
            return $letter;
        }

        $number = $letter->document_type_id === null
            ? $this->serialNumber($letter->running_number)
            : $this->formatNumber($letter, $letter->running_number);

        $letter->update(['document_number' => $number, 'updated_by' => auth()->id()]);

        return $letter->refresh();
    }

    private function formatNumber(OutgoingLetter $letter, int $runningNumber): string
    {
        $letter->loadMissing('documentType');
        $documentCode = $letter->documentType?->code;

        if (! array_key_exists($documentCode, $this->kinds())) {
            throw ValidationException::withMessages(['document_type_id' => 'Jenis surat HR APCA tidak valid.']);
        }

        $date = Carbon::parse($letter->document_date);
        $departmentCode = $documentCode === 'SKK' ? 'HC' : 'HR';

        return sprintf(
            '%03d/ATE-%s/%s/%s/%d',
            $runningNumber,
            $departmentCode,
            $documentCode,
            $this->romanMonth($date->month),
            $date->year,
        );
    }

    private function serialNumber(int $runningNumber): string
    {
        return str_pad((string) $runningNumber, 3, '0', STR_PAD_LEFT);
    }

    private function template(): ?DocumentNumberingTemplate
    {
        return DocumentNumberingTemplate::query()
            ->whereHas('profile', fn ($query) => $query->where('code', 'HR-APCA'))
            ->where('name', 'Nomor urut HR APCA')
            ->where('is_active', true)
            ->first();
    }

    private function ensureConfiguration(): array
    {
        $company = PermitCompany::query()->where('code', 'APCA')->first();
        $department = Department::query()->where('code', 'HR')->first();

        if ($company === null || $department === null) {
            throw ValidationException::withMessages([
                'profile' => 'Entitas APCA atau departemen HR belum tersedia pada master data.',
            ]);
        }

        $profile = LetterProfile::query()->firstOrCreate(
            ['code' => 'HR-APCA'],
            [
                'name' => 'HR APCA',
                'permit_company_id' => $company->id,
                'department_id' => $department->id,
                'form_variant' => 'hr',
                'description' => 'Register nomor surat HR APCA.',
                'is_active' => true,
            ],
        );

        $template = DocumentNumberingTemplate::query()->firstOrCreate(
            [
                'letter_profile_id' => $profile->id,
                'permit_company_id' => $company->id,
                'name' => 'Nomor urut HR APCA',
            ],
            [
                'template' => '{running:3}',
                'running_digits' => 3,
                'reset_period' => 'never',
                'priority' => 100,
                'is_active' => true,
            ],
        );

        foreach ($this->kinds() as $code => $name) {
            DocumentType::query()->firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'is_active' => true],
            );
        }

        return [$profile, $template];
    }

    private function typeFor(string $kind): DocumentType
    {
        if (! array_key_exists($kind, $this->kinds())) {
            throw ValidationException::withMessages(['letter_kind' => 'Jenis surat HR APCA tidak valid.']);
        }

        return DocumentType::query()->firstOrCreate(
            ['code' => $kind],
            ['name' => $this->kinds()[$kind], 'is_active' => true],
        );
    }

    private function romanMonth(int $month): string
    {
        return [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'][$month];
    }
}
