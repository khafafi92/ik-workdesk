<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TYPES = [
        'ATE-PERDIN' => ['Surat Perdin', 'Surat Perdin (A.01)'],
        'ATE-TUGAS' => ['Surat Tugas', 'Surat Tugas (B.01)'],
        'ATE-PERNYATAAN' => ['Surat Pernyataan', 'Surat Pernyataan (B.01)'],
        'ATE-PENGANTAR' => ['Surat Pengantar', 'Surat Pengantar (B.01)'],
        'ATE-PERMOHONAN' => ['Surat Permohonan', 'Surat Permohonan (B.01)'],
        'ATE-KEPUTUSAN' => ['Surat Keputusan', 'Surat Keputusan (A.01)'],
        'ATE-SKET' => ['SKET', 'SKET (B.01)'],
        'ATE-TTD' => ['Tanda Terima Dok', 'Tanda Terima Dokumen (B.01)'],
        'ATE-INFO-MEMO-LWK' => ['Info Memo Luwuk', 'Info Memo Luwuk (FIN-LWK)'],
    ];

    private const TEMPLATE = '{running:3}/{department_code}-ATE/{roman_month}/{year}';

    public function up(): void
    {
        $companyId = DB::table('permit_companies')->where('code', 'APCA')->value('id');
        $profileId = DB::table('letter_profiles')
            ->where('code', 'ATE-UMUM')
            ->where('permit_company_id', $companyId)
            ->value('id');

        $typeIds = DB::table('document_types')
            ->whereIn('code', array_keys(self::TYPES))
            ->pluck('id', 'code');

        foreach (self::TYPES as $code => [$name]) {
            if ($typeIds->has($code)) {
                DB::table('document_types')->where('id', $typeIds->get($code))->update([
                    'name' => $name,
                    'updated_at' => now(),
                ]);
            }
        }

        if ($companyId === null || $profileId === null || $typeIds->isEmpty()) {
            return;
        }

        DB::table('document_numbering_templates')
            ->where('letter_profile_id', $profileId)
            ->where('permit_company_id', $companyId)
            ->whereIn('document_type_id', $typeIds->values())
            ->whereNull('department_id')
            ->update([
                'template' => self::TEMPLATE,
                'running_digits' => 3,
                'reset_period' => 'yearly',
                'is_active' => true,
                'updated_at' => now(),
            ]);

        $departmentTemplates = DB::table('document_numbering_templates')
            ->where('letter_profile_id', $profileId)
            ->where('permit_company_id', $companyId)
            ->whereIn('document_type_id', $typeIds->values())
            ->whereNotNull('department_id')
            ->get();

        foreach ($departmentTemplates as $departmentTemplate) {
            $canonicalTemplate = DB::table('document_numbering_templates')
                ->where('letter_profile_id', $profileId)
                ->where('permit_company_id', $companyId)
                ->where('document_type_id', $departmentTemplate->document_type_id)
                ->whereNull('department_id')
                ->orderByDesc('priority')
                ->orderBy('id')
                ->first();

            if ($canonicalTemplate === null) {
                throw new \RuntimeException("No department-independent APCA template exists for document type {$departmentTemplate->document_type_id}.");
            }

            $sequences = DB::table('document_number_sequences')
                ->where('document_numbering_template_id', $departmentTemplate->id)
                ->get();

            foreach ($sequences as $sequence) {
                $year = $sequence->year;
                $scopeKey = implode('|', [
                    $canonicalTemplate->id,
                    $companyId,
                    'all',
                    $departmentTemplate->document_type_id,
                    $year ?? 'all',
                    'all',
                ]);
                $timestamp = now();

                DB::table('document_number_sequences')->insertOrIgnore([
                    'document_numbering_template_id' => $canonicalTemplate->id,
                    'permit_company_id' => $companyId,
                    'department_id' => null,
                    'document_type_id' => $departmentTemplate->document_type_id,
                    'year' => $year,
                    'month' => null,
                    'scope_key' => $scopeKey,
                    'last_number' => $sequence->last_number,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                $currentNumber = DB::table('document_number_sequences')
                    ->where('scope_key', $scopeKey)
                    ->value('last_number');

                if ((int) $sequence->last_number > (int) $currentNumber) {
                    DB::table('document_number_sequences')->where('scope_key', $scopeKey)->update([
                        'last_number' => $sequence->last_number,
                        'updated_at' => $timestamp,
                    ]);
                }
            }

            DB::table('document_numbering_templates')->where('id', $departmentTemplate->id)->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $typeIds = DB::table('document_types')
            ->whereIn('code', array_keys(self::TYPES))
            ->pluck('id', 'code');

        foreach (self::TYPES as $code => [, $oldName]) {
            if ($typeIds->has($code)) {
                DB::table('document_types')->where('id', $typeIds->get($code))->update([
                    'name' => $oldName,
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
