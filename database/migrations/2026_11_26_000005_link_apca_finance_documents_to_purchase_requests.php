<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_requests', function (Blueprint $table): void {
            $table->foreignId('procurement_id')->nullable()->constrained('scm_requests')->restrictOnDelete();
            $table->unique(['procurement_id', 'kind']);
        });
    }

    public function down(): void
    {
        if (DB::table('finance_requests')->whereNotNull('procurement_id')->exists()) {
            throw new RuntimeException('Pertahankan tautan pengajuan APCA ke PR yang sudah tersimpan.');
        }
        Schema::table('finance_requests', function (Blueprint $table): void {
            $table->dropUnique(['procurement_id', 'kind']);
            $table->dropConstrainedForeignId('procurement_id');
        });
    }
};
