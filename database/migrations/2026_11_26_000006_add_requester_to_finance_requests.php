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
            $table->foreignId('requester_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->index('requester_id');
        });
    }

    public function down(): void
    {
        if (DB::table('finance_requests')->whereNotNull('requester_id')->whereColumn('requester_id', '!=', 'created_by')->exists()) {
            throw new RuntimeException('Dokumen Finance atas nama pemohon lain masih tersimpan.');
        }
        Schema::table('finance_requests', function (Blueprint $table): void {
            $table->dropIndex(['requester_id']);
            $table->dropConstrainedForeignId('requester_id');
        });
    }
};
