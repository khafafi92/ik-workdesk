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
            $table->foreignId('payment_voucher_id')->nullable()->constrained('finance_requests')->restrictOnDelete();
        });
        DB::table('permissions')->where('code', 'finance.requests.create')
            ->update(['name' => 'Buat Payment Voucher dan Reimbursement', 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (DB::table('finance_requests')->whereNotNull('payment_voucher_id')->exists()) {
            throw new RuntimeException('Pertahankan referensi Payment Voucher pada Reimbursement yang sudah tersimpan.');
        }
        Schema::table('finance_requests', fn (Blueprint $table) => $table->dropConstrainedForeignId('payment_voucher_id'));
        DB::table('permissions')->where('code', 'finance.requests.create')
            ->update(['name' => 'Buat Payment Request dan Reimbursement', 'updated_at' => now()]);
    }
};
