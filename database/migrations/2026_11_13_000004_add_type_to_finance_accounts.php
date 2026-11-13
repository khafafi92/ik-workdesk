<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_accounts', fn (Blueprint $table) => $table->string('account_type')->nullable());
    }

    public function down(): void
    {
        if (DB::table('finance_accounts')->whereNotNull('account_type')->exists()) {
            throw new RuntimeException('Pertahankan tipe COA yang sudah diisi.');
        }
        Schema::table('finance_accounts', fn (Blueprint $table) => $table->dropColumn('account_type'));
    }
};
