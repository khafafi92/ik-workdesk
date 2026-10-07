<?php

namespace App\Services;

use App\Imports\KpmogProjectLetterRowsImport;
use App\Models\Department;
use App\Models\DocumentNumberingTemplate;
use App\Models\OutgoingLetter;
use App\Models\User;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class KpmogProjectLetterImportService
{
    /**
     * @return array{created: int, skippedExisting: int, skippedDuplicate: int, skippedInvalid: int, messages: array<int, string>}
     */
    public function import(string|UploadedFile|TemporaryUploadedFile $path, User $actor): array
    {
        $resolvedPath = $this->resolveImportPath($path);

        $reader = new KpmogProjectLetterRowsImport;
        Excel::import($reader, $resolvedPath);
        $rows = ($reader->rows ?? collect())
            ->filter(fn ($row): bool => collect($row)->contains(fn ($value): bool => filled($value)))
            ->values();

        $result = [
            'created' => 0,
            'skippedExisting' => 0,
            'skippedDuplicate' => 0,
            'skippedInvalid' => 0,
            'messages' => [],
        ];

        if ($rows->isEmpty()) {
            $result['messages'][] = 'File tidak memiliki baris data.';

            return $result;
        }

        [$profile, $template] = app(KpmogProjectLetterService::class)->configuration();
        $numbersInFile = [];

        DB::transaction(function () use ($rows, $actor, $profile, $template, &$numbersInFile, &$result): void {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;
                $number = $this->value($row, 'nomor_surat_terbit', 'nomor_surat', 'document_number');

                if (blank($number)) {
                    $result['skippedInvalid']++;
                    $result['messages'][] = "Baris {$rowNumber}: nomor surat terbit wajib diisi.";

                    continue;
                }

                $numberKey = strtolower($number);
                if (isset($numbersInFile[$numberKey])) {
                    $result['skippedDuplicate']++;
                    $result['messages'][] = "Baris {$rowNumber}: nomor {$number} sama dengan baris {$numbersInFile[$numberKey]} dan dilewati.";

                    continue;
                }
                $numbersInFile[$numberKey] = $rowNumber;

                if (OutgoingLetter::query()
                    ->where('permit_company_id', $profile->permit_company_id)
                    ->whereRaw('LOWER(document_number) = ?', [$numberKey])
                    ->exists()) {
                    $result['skippedExisting']++;
                    $result['messages'][] = "Baris {$rowNumber}: nomor {$number} sudah ada di register dan dilewati.";

                    continue;
                }

                $date = $this->date($this->value($row, 'tanggal_surat', 'document_date'));
                if ($date === null) {
                    $result['skippedInvalid']++;
                    $result['messages'][] = "Baris {$rowNumber}: tanggal surat tidak valid.";

                    continue;
                }

                $departmentCode = $this->value($row, 'dept', 'department');
                if (blank($departmentCode)) {
                    $result['skippedInvalid']++;
                    $result['messages'][] = "Baris {$rowNumber}: Dept wajib diisi.";

                    continue;
                }

                $department = $this->department($departmentCode);
                $status = $this->status($this->value($row, 'status_surat', 'status'));
                $runningNumber = $this->runningNumber($number);

                OutgoingLetter::query()->create([
                    'letter_profile_id' => $profile->id,
                    'permit_company_id' => $profile->permit_company_id,
                    'department_id' => $department->id,
                    'document_numbering_template_id' => $template->id,
                    'running_number' => $runningNumber,
                    'document_number' => $number,
                    'document_date' => $date,
                    'pin' => $this->value($row, 'pin'),
                    'pic_name' => $this->value($row, 'pic'),
                    'recipient' => $this->value($row, 'tujuan_surat', 'recipient'),
                    'subject' => $this->value($row, 'perihal_surat', 'subject') ?? 'Belum diisi',
                    'status' => $status,
                    'is_legacy_number' => true,
                    'issued_at' => $status === 'issued' ? $date->copy()->endOfDay() : null,
                    'issued_by' => $status === 'issued' ? $actor->id : null,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);

                if ($runningNumber !== null) {
                    $this->syncSequence($template, $profile->permit_company_id, $date->year, $runningNumber);
                }

                $result['created']++;
            }
        });

        return $result;
    }

    private function resolveImportPath(string|UploadedFile|TemporaryUploadedFile $path): string
    {
        if ($path instanceof UploadedFile && method_exists($path, 'getRealPath')) {
            $realPath = $path->getRealPath();

            if (is_string($realPath) && file_exists($realPath)) {
                return $realPath;
            }
        }

        if (is_string($path) && ! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $storagePath = Storage::disk('local')->path($path);

            if (file_exists($storagePath)) {
                return $storagePath;
            }
        }

        return is_string($path) ? $path : $path->getPathname();
    }

    private function value(array|Collection $row, string ...$keys): ?string
    {
        $values = $row instanceof Collection ? $row : collect($row);

        foreach ($keys as $key) {
            $value = $values->get($key);
            if (filled($value)) {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function date(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            if ($value instanceof DateTimeInterface) {
                return Carbon::instance($value)->startOfDay();
            }

            if (is_numeric($value) && (float) $value > 1000) {
                return Carbon::instance(Date::excelToDateTimeObject((float) $value))->startOfDay();
            }

            return Carbon::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function department(string $code): Department
    {
        $normalized = strtoupper(trim($code));

        return Department::query()
            ->whereRaw('UPPER(code) = ?', [$normalized])
            ->first() ?? Department::query()->create([
                'code' => $normalized,
                'name' => $normalized,
                'is_active' => true,
            ]);
    }

    private function status(?string $value): string
    {
        return match (strtolower(trim((string) $value))) {
            'archived', 'terbit', 'issued' => 'issued',
            'cancelled', 'canceled', 'dibatalkan' => 'cancelled',
            default => 'draft',
        };
    }

    private function runningNumber(string $number): ?int
    {
        return preg_match('/^(\d+)/', trim($number), $matches) === 1 ? (int) $matches[1] : null;
    }

    private function syncSequence(DocumentNumberingTemplate $template, int $companyId, int $year, int $number): void
    {
        $scopeKey = implode('|', [$template->id, $companyId, 'all', 'all', $year, 'all']);

        DB::table('document_number_sequences')->insertOrIgnore([
            'document_numbering_template_id' => $template->id,
            'permit_company_id' => $companyId,
            'department_id' => null,
            'document_type_id' => null,
            'year' => $year,
            'month' => null,
            'scope_key' => $scopeKey,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = DB::table('document_number_sequences')->where('scope_key', $scopeKey)->lockForUpdate()->first();
        if ((int) $sequence->last_number < $number) {
            DB::table('document_number_sequences')
                ->where('id', $sequence->id)
                ->update(['last_number' => $number, 'updated_at' => now()]);
        }
    }
}
