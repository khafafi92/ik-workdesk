<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scm_vendor_portal_accesses', function (Blueprint $table): void {
            $table->text('access_code')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('scm_vendor_portal_accesses')->whereNotNull('access_code')->exists()) {
            throw new RuntimeException('Vendor ID sudah disimpan. Pertahankan kode akses vendor.');
        }
        Schema::table('scm_vendor_portal_accesses', function (Blueprint $table): void {
            $table->dropColumn('access_code');
        });
    }
};
