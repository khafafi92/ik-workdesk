<?php

namespace App\Services;

use App\Models\DocumentNumberingTemplate;
use App\Models\OutgoingLetter;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentNumberService
{
    public function resolveFor(OutgoingLetter $letter): ?DocumentNumberingTemplate
    {
        return DocumentNumberingTemplate::query()
            ->where('is_active', true)
            ->where('permit_company_id', $letter->permit_company_id)
            ->where(fn ($query) => $query
                ->whereNull('letter_profile_id')
                ->orWhere('letter_profile_id', $letter->letter_profile_id))
            ->where(fn ($query) => $query
                ->whereNull('department_id')
                ->orWhere('department_id', $letter->department_id))
            ->where(fn ($query) => $query
                ->whereNull('document_type_id')
                ->orWhere('document_type_id', $letter->document_type_id))
            ->orderByRaw('CASE WHEN letter_profile_id IS NULL THEN 0 ELSE 1 END + CASE WHEN department_id IS NULL THEN 0 ELSE 1 END + CASE WHEN document_type_id IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByDesc('priority')
            ->orderBy('id')
            ->first();
    }

    public function preview(OutgoingLetter $letter, ?DocumentNumberingTemplate $template = null, int $runningNumber = 1): string
    {
        $template ??= $this->resolveFor($letter);

        if (! $template) {
            return 'Belum ada template nomor yang sesuai.';
        }

        return $this->format($template, $letter, $runningNumber);
    }

    public function issue(OutgoingLetter $letter, User $issuer): OutgoingLetter
    {
        return DB::transaction(function () use ($letter, $issuer): OutgoingLetter {
            $lockedLetter = OutgoingLetter::query()->lockForUpdate()->findOrFail($letter->getKey());

            if ($lockedLetter->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Hanya surat draft yang dapat diterbitkan.']);
            }

            if ($lockedLetter->is_legacy_number) {
                if (blank($lockedLetter->document_number)) {
                    throw ValidationException::withMessages(['document_number' => 'Nomor surat existing wajib diisi.']);
                }

                $lockedLetter->update([
                    'status' => 'issued',
                    'issued_at' => now(),
                    'issued_by' => $issuer->id,
                    'updated_by' => $issuer->id,
                ]);

                return $lockedLetter->refresh();
            }

            if (blank($lockedLetter->document_number)) {
                $this->reserveLocked($lockedLetter);
            }

            $lockedLetter->update([
                'status' => 'issued',
                'issued_at' => now(),
                'issued_by' => $issuer->id,
                'updated_by' => $issuer->id,
            ]);

            return $lockedLetter->refresh();
        });
    }

    public function reserve(OutgoingLetter $letter, ?callable $numberFormatter = null): OutgoingLetter
    {
        return DB::transaction(function () use ($letter, $numberFormatter): OutgoingLetter {
            $lockedLetter = OutgoingLetter::query()->lockForUpdate()->findOrFail($letter->getKey());

            if ($lockedLetter->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Hanya surat draft yang dapat menggunakan nomor.']);
            }

            if (filled($lockedLetter->document_number)) {
                return $lockedLetter->refresh();
            }

            if ($lockedLetter->is_legacy_number) {
                throw ValidationException::withMessages(['document_number' => 'Nomor existing harus diisi secara manual.']);
            }

            $this->reserveLocked($lockedLetter, $numberFormatter);

            return $lockedLetter->refresh();
        });
    }

    public function format(DocumentNumberingTemplate $template, OutgoingLetter $letter, int $runningNumber): string
    {
        $this->validateTemplate($template);
        $letter->loadMissing(['company', 'department', 'documentType', 'project']);
        $date = Carbon::parse($letter->document_date);
        $values = [
            'company_code' => $letter->company?->code,
            'department_code' => $letter->department?->code,
            'document_code' => $letter->documentType?->code,
            'roman_month' => $this->romanMonth($date->month),
            'month' => $date->format('m'),
            'year' => $date->format('Y'),
            'year_short' => $date->format('y'),
            'location_code' => $letter->location_code,
            'project_code' => $letter->project?->code,
        ];

        return preg_replace_callback('/\{([a-z_]+)(?::(\d+))?\}/', function (array $matches) use ($template, $values, $runningNumber): string {
            $token = $matches[1];

            if ($token === 'running') {
                $digits = isset($matches[2]) ? (int) $matches[2] : $template->running_digits;

                if ($digits < 1 || $digits > 10) {
                    throw ValidationException::withMessages(['template' => 'Panjang token running harus antara 1 sampai 10 digit.']);
                }

                return str_pad((string) $runningNumber, $digits, '0', STR_PAD_LEFT);
            }

            if (! array_key_exists($token, $values)) {
                throw ValidationException::withMessages(['template' => "Token {{$token}} tidak didukung."]);
            }

            if (blank($values[$token])) {
                throw ValidationException::withMessages(['template' => "Data untuk token {{$token}} belum tersedia."]);
            }

            return (string) $values[$token];
        }, $template->template) ?? $template->template;
    }

    private function nextRunningNumber(DocumentNumberingTemplate $template, OutgoingLetter $letter): int
    {
        $date = Carbon::parse($letter->document_date);
        [$year, $month] = match ($template->reset_period) {
            'monthly' => [$date->year, $date->month],
            'yearly' => [$date->year, null],
            'never' => [null, null],
            default => throw ValidationException::withMessages(['reset_period' => 'Periode reset template tidak valid.']),
        };
        $scopeKey = implode('|', [
            $template->id,
            $letter->permit_company_id,
            $template->department_id ?? 'all',
            $template->document_type_id ?? 'all',
            $year ?? 'all',
            $month ?? 'all',
        ]);

        DB::table('document_number_sequences')->insertOrIgnore([
            'document_numbering_template_id' => $template->id,
            'permit_company_id' => $letter->permit_company_id,
            'department_id' => $template->department_id,
            'document_type_id' => $template->document_type_id,
            'year' => $year,
            'month' => $month,
            'scope_key' => $scopeKey,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = DB::table('document_number_sequences')
            ->where('scope_key', $scopeKey)
            ->lockForUpdate()
            ->first();
        $nextNumber = ((int) $sequence->last_number) + 1;

        DB::table('document_number_sequences')
            ->where('id', $sequence->id)
            ->update(['last_number' => $nextNumber, 'updated_at' => now()]);

        return $nextNumber;
    }

    private function reserveLocked(OutgoingLetter $letter, ?callable $numberFormatter = null): void
    {
        $template = $this->resolveFor($letter);

        if (! $template) {
            throw ValidationException::withMessages(['document_numbering_template_id' => 'Template nomor surat tidak ditemukan untuk kombinasi data ini.']);
        }

        $runningNumber = $this->nextRunningNumber($template, $letter);

        $letter->update([
            'document_numbering_template_id' => $template->id,
            'running_number' => $runningNumber,
            'document_number' => $numberFormatter
                ? $numberFormatter($template, $letter, $runningNumber)
                : $this->format($template, $letter, $runningNumber),
            'updated_by' => auth()->id(),
        ]);
    }

    private function romanMonth(int $month): string
    {
        return [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'][$month];
    }

    private function validateTemplate(DocumentNumberingTemplate $template): void
    {
        preg_match_all('/\{([^}]+)\}/', $template->template, $matches);

        foreach ($matches[1] as $token) {
            if (! preg_match('/^(running(?::\d+)?|company_code|department_code|document_code|roman_month|month|year|year_short|location_code|project_code)$/', $token)) {
                throw ValidationException::withMessages(['template' => "Token {{$token}} tidak didukung."]);
            }
        }
    }
}
