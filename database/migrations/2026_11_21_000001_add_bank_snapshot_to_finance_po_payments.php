<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_po_payments', function (Blueprint $table): void {
            $table->json('vendor_bank_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('finance_po_payments', function (Blueprint $table): void {
            $table->dropColumn('vendor_bank_snapshot');
        });
    }
};
