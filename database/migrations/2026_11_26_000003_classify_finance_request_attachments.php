<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_request_files', function (Blueprint $table): void {
            $table->string('kind', 15)->default('support');
        });
    }

    public function down(): void
    {
        if (DB::table('finance_request_files')->where('kind', 'invoice')->exists()) {
            throw new RuntimeException('Pertahankan klasifikasi invoice yang sudah tersimpan.');
        }
        Schema::table('finance_request_files', fn (Blueprint $table) => $table->dropColumn('kind'));
    }
};
