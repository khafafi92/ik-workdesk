<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scm_approval_routes', function (Blueprint $table): void {
            $table->foreignId('cbo3_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('scm_approval_routes')->whereNull('cbo3_id')->exists()) {
            throw new RuntimeException('CBO 3 belum lengkap; migrasi tidak dapat dibatalkan.');
        }
        Schema::table('scm_approval_routes', function (Blueprint $table): void {
            $table->foreignId('cbo3_id')->nullable(false)->change();
        });
    }
};
