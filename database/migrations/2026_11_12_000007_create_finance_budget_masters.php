<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_budgets', function (Blueprint $table): void {
            $table->id();
            $table->string('company', 20);
            $table->unsignedSmallInteger('year');
            $table->bigInteger('amount_minor');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company', 'year']);
        });
        Schema::create('finance_budget_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('budget_id')->constrained('finance_budgets')->restrictOnDelete();
            $table->string('need_type', 20);
            $table->foreignId('project_numbering_id')->nullable()->constrained('scm_project_numbering')->restrictOnDelete();
            $table->string('scope_key', 80);
            $table->bigInteger('amount_minor');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['budget_id', 'scope_key']);
        });
        Schema::create('finance_budget_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scope_id')->constrained('finance_budget_scopes')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('account_id')->constrained('finance_accounts')->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['scope_id', 'department_id', 'account_id']);
        });
        DB::table('permissions')->insertOrIgnore(['code' => 'finance.budget.manage', 'name' => 'Kelola master anggaran',
            'module' => 'Finance', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (DB::table('finance_budgets')->exists()) {
            throw new RuntimeException('Pertahankan data anggaran yang sudah diisi.');
        }
        Schema::dropIfExists('finance_budget_lines');
        Schema::dropIfExists('finance_budget_scopes');
        Schema::dropIfExists('finance_budgets');
    }
};
