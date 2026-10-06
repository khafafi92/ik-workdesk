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

class AteGeneralLetterService
{
    public function kinds(): array
    {
        $definitions = $this->definitions();
        $types = DocumentType::query()
            ->whereIn('code', collect($definitions)->pluck('type_code'))
            ->get()
            ->keyBy('code');

        return collect($definitions)
            ->mapWithKeys(function (array $definition, string $kind) use ($types): array {
                $type = $types->get($definition['type_code']);

                if ($type !== null && ! $type->is_active) {
                    return [];
                }

                return [$kind => $definition['label']];
            })
            ->all();
    }

    public function createDraft(User $user, string $date, string $kind, int $departmentId): OutgoingLetter
    {
        $this->definition($kind);
        $department = $this->departmentById($departmentId);
        [$profile] = $this->ensureConfiguration();
        $type = $this->typeFor($kind);

        if (! $type->is_active) {
            throw ValidationException::withMessages(['letter_kind' => 'Jenis surat APCA ini tidak aktif.']);
        }

        $letter = new OutgoingLetter([
            'letter_profile_id' => $profile->id,
            'permit_company_id' => $profile->permit_company_id,
            'department_id' => $department->id,
            'document_type_id' => $type->id,
            'document_date' => $date,
            'subject' => 'Belum diisi',
            'status' => 'draft',
        ]);
        $letter->forceFill([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ])->save();

        return app(DocumentNumberService::class)->reserve(
            $letter,
            fn (DocumentNumberingTemplate $template, OutgoingLetter $draft, int $runningNumber): string => $this->formatNumber($draft, $runningNumber),
        );
    }

    public function refreshNumber(OutgoingLetter $letter): OutgoingLetter
    {
        if ($letter->status !== 'draft' || blank($letter->running_number)) {
            return $letter;
        }

        $letter->load('department');
        $department = $letter->department;

        if ($department === null || ! $department->is_active || blank($department->code)) {
            throw ValidationException::withMessages([
                'department_id' => 'Pilih departemen aktif sebelum menyimpan atau menerbitkan surat.',
            ]);
        }

        $letter->update([
            'department_id' => $department->id,
            'document_number' => $this->formatNumber($letter, $letter->running_number, $department),
            'updated_by' => auth()->id(),
        ]);

        return $letter->refresh();
    }

    private function definitions(): array
    {
        return [
            'perdin' => ['label' => 'Surat Perdin', 'type_code' => 'ATE-PERDIN'],
            'tugas' => ['label' => 'Surat Tugas', 'type_code' => 'ATE-TUGAS'],
            'pernyataan' => ['label' => 'Surat Pernyataan', 'type_code' => 'ATE-PERNYATAAN'],
            'pengantar' => ['label' => 'Surat Pengantar', 'type_code' => 'ATE-PENGANTAR'],
            'permohonan' => ['label' => 'Surat Permohonan', 'type_code' => 'ATE-PERMOHONAN'],
            'keputusan' => ['label' => 'Surat Keputusan', 'type_code' => 'ATE-KEPUTUSAN'],
            'sket' => ['label' => 'SKET', 'type_code' => 'ATE-SKET'],
            'tanda_terima_dok' => ['label' => 'Tanda Terima Dok', 'type_code' => 'ATE-TTD'],
            'info_memo_luwuk' => ['label' => 'Info Memo Luwuk', 'type_code' => 'ATE-INFO-MEMO-LWK'],
        ];
    }

    private function definition(string $kind): array
    {
        $definition = $this->definitions()[$kind] ?? null;

        if ($definition === null) {
            throw ValidationException::withMessages(['letter_kind' => 'Pilih jenis surat ATE yang tersedia.']);
        }

        return $definition;
    }

    private function formatNumber(OutgoingLetter $letter, int $runningNumber, ?Department $department = null): string
    {
        $letter->loadMissing(['department', 'documentType']);
        $typeExists = collect($this->definitions())
            ->contains(fn (array $item): bool => $item['type_code'] === $letter->documentType?->code);

        if (! $typeExists) {
            throw ValidationException::withMessages(['document_type_id' => 'Jenis surat ATE tidak valid.']);
        }

        $department ??= $letter->department;

        if ($department === null || blank($department->code)) {
            throw ValidationException::withMessages(['department_id' => 'Pilih departemen aktif dari master departemen.']);
        }

        $date = Carbon::parse($letter->document_date);
        $running = str_pad((string) $runningNumber, 3, '0', STR_PAD_LEFT);
        $month = $this->romanMonth($date->month);

        return "{$running}/{$department->code}-ATE/{$month}/{$date->year}";
    }

    private function departmentById(int $departmentId): Department
    {
        $department = Department::query()
            ->whereKey($departmentId)
            ->where('is_active', true)
            ->first();

        if ($department === null || ! $department->is_active || blank($department->code)) {
            throw ValidationException::withMessages(['department_id' => 'Pilih departemen aktif dari master departemen.']);
        }

        return $department;
    }

    private function ensureConfiguration(): array
    {
        $company = PermitCompany::query()->where('code', 'APCA')->first();

        if ($company === null) {
            throw ValidationException::withMessages(['profile' => 'Entitas APCA belum tersedia pada master data.']);
        }

        $profile = LetterProfile::query()->firstOrCreate(
            ['code' => 'ATE-UMUM'],
            [
                'name' => 'Surat Umum APCA',
                'permit_company_id' => $company->id,
                'form_variant' => 'general',
                'description' => 'Register surat umum APCA dengan nomor berdasarkan departemen yang dipilih.',
                'is_active' => true,
            ],
        );

        foreach ($this->definitions() as $kind => $definition) {
            $type = $this->typeFor($kind);
            DocumentNumberingTemplate::query()->firstOrCreate(
                [
                    'letter_profile_id' => $profile->id,
                    'permit_company_id' => $company->id,
                    'document_type_id' => $type->id,
                    'name' => 'Nomor ATE '.strtoupper($kind),
                ],
                [
                    'template' => '{running:3}/{department_code}-ATE/{roman_month}/{year}',
                    'running_digits' => 3,
                    'reset_period' => 'yearly',
                    'priority' => 100,
                    'is_active' => true,
                ],
            );
        }

        return [$profile];
    }

    private function typeFor(string $kind): DocumentType
    {
        $definition = $this->definition($kind);

        return DocumentType::query()->firstOrCreate(
            ['code' => $definition['type_code']],
            ['name' => $definition['label'], 'is_active' => true],
        );
    }

    private function romanMonth(int $month): string
    {
        return [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'][$month];
    }
}
