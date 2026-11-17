<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scm_vendors', function (Blueprint $table): void {
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number', 100)->nullable();
            $table->string('bank_account_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('scm_vendors', function (Blueprint $table): void {
            $table->dropColumn(['bank_name', 'bank_account_number', 'bank_account_name']);
        });
    }
};
