<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scm_po_approval_policies', function (Blueprint $table): void {
            $table->foreignId('second_buyer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('second_buyer_position')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('scm_po_approval_policies')->whereNotNull('second_buyer_id')->exists()) {
            throw new RuntimeException('Pertahankan pengaturan penandatangan buyer kedua.');
        }
        Schema::table('scm_po_approval_policies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('second_buyer_id');
            $table->dropColumn('second_buyer_position');
        });
    }
};
