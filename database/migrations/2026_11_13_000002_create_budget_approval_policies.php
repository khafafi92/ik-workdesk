<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_budget_approval_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('company', 20);
            $table->string('need_type', 20);
            $table->foreignId('finance_manager_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('cbo_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('coo_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['company', 'need_type']);
        });
    }

    public function down(): void
    {
        if (DB::table('finance_budget_approval_policies')->exists()) {
            throw new RuntimeException('Pertahankan aturan persetujuan anggaran.');
        }
        Schema::dropIfExists('finance_budget_approval_policies');
    }
};
