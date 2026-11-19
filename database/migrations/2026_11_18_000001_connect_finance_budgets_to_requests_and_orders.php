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
            $table->foreignId('finance_budget_line_id')->nullable()->after('budget_estimate_minor')
                ->constrained('finance_budget_lines')->restrictOnDelete();
        });

        Schema::table('travel_documents', function (Blueprint $table): void {
            $table->foreignId('finance_budget_line_id')->nullable()->after('project_numbering_id')
                ->constrained('finance_budget_lines')->restrictOnDelete();
        });

        Schema::table('scm_purchase_orders', function (Blueprint $table): void {
            $table->string('status', 20)->default('active')->after('snapshot');
            $table->foreignId('cancelled_by')->nullable()->after('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            $table->text('cancellation_reason')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        if (DB::table('scm_requests')->whereNotNull('finance_budget_line_id')->exists()
            || DB::table('travel_documents')->whereNotNull('finance_budget_line_id')->exists()
            || DB::table('scm_purchase_orders')->where('status', 'cancelled')->exists()) {
            throw new RuntimeException('Pertahankan relasi dan riwayat pembatalan anggaran yang sudah tercatat.');
        }

        Schema::table('scm_purchase_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['status', 'cancelled_at', 'cancellation_reason']);
        });
        Schema::table('travel_documents', fn (Blueprint $table) => $table->dropConstrainedForeignId('finance_budget_line_id'));
        Schema::table('scm_requests', fn (Blueprint $table) => $table->dropConstrainedForeignId('finance_budget_line_id'));
    }
};
