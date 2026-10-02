<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('document_numbering_templates')
            ->whereIn('letter_profile_id', DB::table('letter_profiles')->where('code', 'HR-APCA')->select('id'))
            ->where('name', 'Nomor urut HR APCA')
            ->where('template', '{running:3}')
            ->update([
                'template' => '{running:3}/ATE-{department_code}/{document_code}/{roman_month}/{year}',
                'updated_at' => $now,
            ]);

        DB::table('document_numbering_templates')
            ->whereIn('letter_profile_id', DB::table('letter_profiles')->where('code', 'HR-KPMOG')->select('id'))
            ->where('name', 'Nomor urut HR KPMOG')
            ->where('template', '{running:3}')
            ->update([
                'template' => '{running:3}/{company_code}-{department_code}/{roman_month}/{year}',
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        DB::table('document_numbering_templates')
            ->whereIn('letter_profile_id', DB::table('letter_profiles')->where('code', 'HR-APCA')->select('id'))
            ->where('name', 'Nomor urut HR APCA')
            ->where('template', '{running:3}/ATE-{department_code}/{document_code}/{roman_month}/{year}')
            ->update(['template' => '{running:3}', 'updated_at' => now()]);

        DB::table('document_numbering_templates')
            ->whereIn('letter_profile_id', DB::table('letter_profiles')->where('code', 'HR-KPMOG')->select('id'))
            ->where('name', 'Nomor urut HR KPMOG')
            ->where('template', '{running:3}/{company_code}-{department_code}/{roman_month}/{year}')
            ->update(['template' => '{running:3}', 'updated_at' => now()]);
    }
};
