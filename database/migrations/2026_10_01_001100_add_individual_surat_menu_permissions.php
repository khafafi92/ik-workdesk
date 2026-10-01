<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([
            ['letters.outgoing', 'Access Outgoing Letters Menu', 'Open the outgoing-letter menu.'],
            ['letters.document-types', 'Access Document Types Menu', 'Open the document-types menu.'],
            ['letters.numbering-templates', 'Access Numbering Templates Menu', 'Open the numbering-templates menu.'],
        ] as [$code, $name, $description]) {
            DB::table('permissions')->updateOrInsert(['code' => $code], [
                'name' => $name,
                'module' => 'Surat',
                'description' => $description,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('code', [
            'letters.outgoing',
            'letters.document-types',
            'letters.numbering-templates',
        ])->delete();
    }
};
