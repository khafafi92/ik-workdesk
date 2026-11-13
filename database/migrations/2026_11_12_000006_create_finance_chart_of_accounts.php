<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('company', 20);
            $table->string('code', 100);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company', 'code']);
        });
        DB::table('permissions')->insertOrIgnore(['code' => 'finance.coa.manage', 'name' => 'Kelola master COA',
            'module' => 'Finance', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (DB::table('finance_accounts')->exists()) {
            throw new RuntimeException('Pertahankan master COA yang sudah diisi.');
        }
        Schema::dropIfExists('finance_accounts');
    }
};
