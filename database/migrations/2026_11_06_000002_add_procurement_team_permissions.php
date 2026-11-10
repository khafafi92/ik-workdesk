<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['ga.process' => 'Proses pengadaan GA khusus HO', 'scm.process-kpmog' => 'Proses pengadaan Project KPMOG',
            'scm.process-apca' => 'Proses pengadaan Project APCA'] as $code => $name) {
            DB::table('permissions')->insertOrIgnore(['code' => $code, 'name' => $name, 'module' => 'SCM',
                'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Izin tim pengadaan harus ditinjau sebelum migrasi dibatalkan.');
    }
};
