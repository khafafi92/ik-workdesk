<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_budgets', function (Blueprint $table): void {
            $table->string('currency', 10)->default('IDR');
            $table->dropUnique(['company', 'year']);
            $table->unique(['company', 'year', 'currency']);
        });
    }

    public function down(): void
    {
        if (DB::table('finance_budgets')->where('currency', '!=', 'IDR')->exists()) {
            throw new RuntimeException('Pertahankan anggaran dalam mata uang selain IDR.');
        }
        Schema::table('finance_budgets', function (Blueprint $table): void {
            $table->dropUnique(['company', 'year', 'currency']);
            $table->unique(['company', 'year']);
            $table->dropColumn('currency');
        });
    }
};
