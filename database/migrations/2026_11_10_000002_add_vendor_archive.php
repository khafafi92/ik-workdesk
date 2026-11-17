<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scm_vendors', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        if (DB::table('scm_vendors')->whereNotNull('archived_at')->exists()) {
            throw new RuntimeException('Tidak dapat menghapus penanda arsip saat vendor masih diarsipkan.');
        }
        Schema::table('scm_vendors', function (Blueprint $table): void {
            $table->dropIndex(['archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};
