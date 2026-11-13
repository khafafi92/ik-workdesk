<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_accounts', function (Blueprint $table): void {
            $table->foreignId('parent_id')->nullable()->constrained('finance_accounts')->restrictOnDelete();
        });
        Schema::create('finance_account_hierarchy_locks', function (Blueprint $table): void {
            $table->string('company', 20)->primary();
        });
        foreach (array_keys(config('scm.companies')) as $company) {
            DB::table('finance_account_hierarchy_locks')->insert(['company' => $company]);
        }
    }

    public function down(): void
    {
        if (DB::table('finance_accounts')->whereNotNull('parent_id')->exists()) {
            throw new RuntimeException('Pertahankan hierarki COA yang sudah diisi.');
        }
        Schema::table('finance_accounts', fn (Blueprint $table) => $table->dropConstrainedForeignId('parent_id'));
        Schema::dropIfExists('finance_account_hierarchy_locks');
    }
};
