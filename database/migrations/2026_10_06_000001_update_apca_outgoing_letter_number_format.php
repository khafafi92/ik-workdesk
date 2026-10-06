<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const APCA_NUMBER_FORMAT = '{running:3}/{department_code}-ATE/{roman_month}/{year}';

    public function up(): void
    {
        DB::table('document_numbering_templates')
            ->whereIn('letter_profile_id', DB::table('letter_profiles')
                ->where('code', 'ATE-UMUM')
                ->whereIn('permit_company_id', DB::table('permit_companies')->where('code', 'APCA')->select('id'))
                ->select('id'))
            ->whereNotNull('document_type_id')
            ->where('template', '{running:3}')
            ->update([
                'template' => self::APCA_NUMBER_FORMAT,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('document_numbering_templates')
            ->whereIn('letter_profile_id', DB::table('letter_profiles')
                ->where('code', 'ATE-UMUM')
                ->whereIn('permit_company_id', DB::table('permit_companies')->where('code', 'APCA')->select('id'))
                ->select('id'))
            ->where('template', self::APCA_NUMBER_FORMAT)
            ->whereNotNull('document_type_id')
            ->update([
                'template' => '{running:3}',
                'updated_at' => now(),
            ]);
    }
};
