<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scm_requests', function (Blueprint $table): void {
            $table->string('budget_currency', 10)->nullable();
            $table->unsignedBigInteger('budget_estimate_minor')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('scm_requests')->whereNotNull('budget_estimate_minor')->exists()) {
            throw new RuntimeException('Pertahankan estimasi anggaran yang telah dicatat.');
        }
        Schema::table('scm_requests', fn (Blueprint $table) => $table->dropColumn(['budget_currency', 'budget_estimate_minor']));
    }
};
