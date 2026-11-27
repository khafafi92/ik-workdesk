<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE UNIQUE INDEX finance_requests_expense_report_voucher_unique ON finance_requests (payment_voucher_id) WHERE kind = 'expense_report' AND payment_voucher_id IS NOT NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX finance_requests_expense_report_voucher_unique');
    }
};
