<?php

namespace App\Services;

use App\Models\Department;
use App\Models\DocumentNumberingTemplate;
use App\Models\DocumentType;
use App\Models\LetterProfile;
use App\Models\OutgoingLetter;
use App\Models\PermitCompany;
use App\Models\User;
use DateTimeImmutable;
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
        $type = $kind === null ? null : $this->typeFor($kind);
        $department = $this->department();

        $letter = OutgoingLetter::create([
            'letter_profile_id' => $profile->id,
            'permit_company_id' => $profile->permit_company_id,
            'department_id' => $department->id,
            'document_type_id' => $type?->id,
            'document_date' => $date,
            'subject' => 'Belum diisi',
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return app(DocumentNumberService::class)->reserve(
            $letter,
            $kind === null ? fn ($template, $draft, int $number): string => str_pad((string) $number, 3, '0', STR_PAD_LEFT) : null,
        );
    }

    public function prepareDraftData(OutgoingLetter $letter, array $data): array
    {
        $kind = $this->kindFromSubject($data['subject'], $letter->documentType?->code);

        return [
            ...$data,
            'document_type_id' => $this->typeFor($kind)->id,
            'department_id' => $this->department()->id,
        ];
    }

    public function previewNumber(OutgoingLetter $letter, ?string $subject, ?string $date): string
    {
        if ($letter->status !== 'draft' || blank($letter->running_number) || blank($subject)
            || $subject === 'Belum diisi' || blank($date) || $letter->numberingTemplate === null) {
            return $letter->document_number ?? '-';
        }

        $previewDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (! $previewDate || $previewDate->format('Y-m-d') !== $date) {
            return $letter->document_number ?? '-';
        }

        try {
            $kind = $this->kindFromSubject($subject, $letter->documentType?->code);
        } catch (ValidationException) {
            return $letter->document_number ?? '-';
        }

        $preview = clone $letter;
        $preview->document_date = $date;
        $preview->setRelation('department', new Department(['code' => 'HR']));
        $preview->setRelation('documentType', new DocumentType([
            'code' => $kind,
        ]));

        return app(DocumentNumberService::class)->format($letter->numberingTemplate, $preview, $letter->running_number);
    }

    public function refreshNumber(OutgoingLetter $letter): OutgoingLetter
    {
        if ($letter->status !== 'draft' || blank($letter->running_number)) {
            return $letter;
        }

        $letter->load('documentType');
        $documentCode = $letter->documentType?->code;

        if (! $this->isValidCode($documentCode)) {
            throw ValidationException::withMessages(['subject' => 'Lengkapi dan simpan keterangan surat untuk membentuk nomor HR APCA.']);
        }

        $letter->update(['department_id' => $this->department()->id, 'updated_by' => auth()->id()]);
        $letter->refresh();

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
                'template' => '{running:3}/ATE-{department_code}/{document_code}/{roman_month}/{year}',
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
        $kind = strtoupper(trim($kind));

        if (! $this->isValidCode($kind)) {
            throw ValidationException::withMessages(['subject' => 'Kode surat harus berisi 1 sampai 20 huruf atau angka, dimulai dengan huruf. Contoh: [SKC] Surat keterangan kerja.']);
        }

        return DocumentType::query()->where('code', $kind)->first() ?? DocumentType::query()->firstOrCreate(
            ['name' => $this->kinds()[$kind] ?? 'HR APCA - '.$kind],
            ['code' => $kind, 'is_active' => true],
        );
    }

    private function kindFromSubject(string $subject, ?string $existingKind = null): string
    {
        if (str_starts_with(ltrim($subject), '[')) {
            if (! preg_match('/^\s*\[([^\]\r\n]*)\]/', $subject, $matches)
                || ! $this->isValidCode($kind = strtoupper(trim($matches[1])))) {
                throw ValidationException::withMessages(['subject' => 'Tulis kode dalam [ ], berisi 1 sampai 20 huruf atau angka, dimulai dengan huruf. Contoh: [SKC] Surat keterangan kerja.']);
            }

            return $kind;
        }

        if (preg_match('/\b(SKK|SKET)\b/i', $subject, $matches)) {
            return strtoupper($matches[1]);
        }

        if (preg_match('/\bketerangan\s+kerja\b/i', $subject)) {
            return 'SKK';
        }

        if (preg_match('/\bbpjs\b|\bsurat\s+keterangan\b/i', $subject)) {
            return 'SKET';
        }

        return $this->isValidCode($existingKind) ? $existingKind : 'SKET';
    }

    private function isValidCode(?string $code): bool
    {
        return $code !== null && preg_match('/\A[A-Z][A-Z0-9]{0,19}\z/', $code) === 1;
    }

    private function department(): Department
    {
        $department = Department::query()->where('code', 'HR')->where('is_active', true)->first();

        if ($department === null) {
            throw ValidationException::withMessages([
                'department_id' => 'Departemen HR yang aktif belum tersedia pada master data.',
            ]);
        }

        return $department;
    }
}
