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
        return collect($this->definitions())
            ->mapWithKeys(fn (array $definition, string $kind): array => [$kind => $definition['label']])
            ->all();
    }

    public function createDraft(User $user, string $date, string $kind): OutgoingLetter
    {
        [$profile] = $this->ensureConfiguration();
        $definition = $this->definition($kind);
        $type = $this->typeFor($kind);
        $department = $definition['department'] === null ? null : $this->department($definition['department']);

        $letter = OutgoingLetter::query()->create([
            'letter_profile_id' => $profile->id,
            'permit_company_id' => $profile->permit_company_id,
            'department_id' => $department?->id,
            'document_type_id' => $type->id,
            'document_date' => $date,
            'subject' => 'Belum diisi',
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

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

        $letter->update([
            'document_number' => $this->formatNumber($letter, $letter->running_number),
            'updated_by' => auth()->id(),
        ]);

        return $letter->refresh();
    }

    private function definitions(): array
    {
        return [
            'perdin' => ['label' => 'Surat Perdin (A.12)', 'type_code' => 'ATE-PERDIN', 'prefix' => 'A.12', 'department' => null, 'format' => 'standard'],
            'tugas' => ['label' => 'Surat Tugas (B.08)', 'type_code' => 'ATE-TUGAS', 'prefix' => 'B.08', 'department' => null, 'format' => 'standard'],
            'pernyataan' => ['label' => 'Surat Pernyataan (B.11)', 'type_code' => 'ATE-PERNYATAAN', 'prefix' => 'B.11', 'department' => null, 'format' => 'standard'],
            'pengantar' => ['label' => 'Surat Pengantar (B.19)', 'type_code' => 'ATE-PENGANTAR', 'prefix' => 'B.19', 'department' => null, 'format' => 'standard'],
            'permohonan' => ['label' => 'Surat Permohonan (B.09)', 'type_code' => 'ATE-PERMOHONAN', 'prefix' => 'B.09', 'department' => null, 'format' => 'standard'],
            'keputusan' => ['label' => 'Surat Keputusan (A.01)', 'type_code' => 'ATE-KEPUTUSAN', 'prefix' => 'A.01', 'department' => null, 'format' => 'standard'],
            'sket' => ['label' => 'SKET (B.09)', 'type_code' => 'ATE-SKET', 'prefix' => 'B.09', 'department' => null, 'format' => 'standard'],
            'tanda_terima_dok' => ['label' => 'Tanda Terima Dokumen (B.014)', 'type_code' => 'ATE-TTD', 'prefix' => 'B.014', 'department' => null, 'format' => 'receipt'],
            'info_memo_luwuk' => ['label' => 'Info Memo Luwuk (FIN-LWK)', 'type_code' => 'ATE-INFO-MEMO-LWK', 'prefix' => null, 'department' => 'FIN', 'format' => 'memo_luwuk'],
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

    private function formatNumber(OutgoingLetter $letter, int $runningNumber): string
    {
        $letter->loadMissing('documentType');
        $definition = collect($this->definitions())
            ->first(fn (array $item): bool => $item['type_code'] === $letter->documentType?->code);

        if ($definition === null) {
            throw ValidationException::withMessages(['document_type_id' => 'Jenis surat ATE tidak valid.']);
        }

        $date = Carbon::parse($letter->document_date);
        $running = str_pad((string) $runningNumber, 3, '0', STR_PAD_LEFT);
        $month = $this->romanMonth($date->month);

        return match ($definition['format']) {
            'receipt' => "{$definition['prefix']}/TTD-ATE/{$month}/{$date->year}",
            'memo_luwuk' => "{$running}-FIN-LWK-ATE-{$month}-{$date->year}",
            default => "{$definition['prefix']}/{$running}/ATE/{$month}/{$date->year}",
        };
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
                'description' => 'Register surat umum APCA dengan pola nomor ATE berdasarkan jenis surat.',
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
                    'template' => '{running:3}',
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

    private function department(string $code): Department
    {
        return Department::query()->whereRaw('UPPER(code) = ?', [$code])->first()
            ?? Department::query()->create(['code' => $code, 'name' => 'Finance', 'is_active' => true]);
    }

    private function romanMonth(int $month): string
    {
        return [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'][$month];
    }
}
