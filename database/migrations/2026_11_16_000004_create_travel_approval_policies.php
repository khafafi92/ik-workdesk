<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_approval_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('company', 10);
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->string('document_kind', 3)->default('ALL');
            $table->foreignId('hr_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('finance_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['company', 'department_id', 'document_kind'], 'travel_approval_scope_unique');
        });
        Schema::create('travel_approval_policy_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('policy_id');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 30);
            $table->json('details');
            $table->timestamp('created_at');
            $table->index('policy_id');
        });
        DB::table('permissions')->insertOrIgnore(['code' => 'travel.configure', 'name' => 'Kelola Master Approval SPD/LPJ',
            'module' => 'Perjalanan Dinas', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (DB::table('travel_approval_policies')->exists() || DB::table('travel_approval_policy_events')->exists()) {
            throw new RuntimeException('Master approval dan riwayat SPD/LPJ harus dipertahankan.');
        }
        Schema::dropIfExists('travel_approval_policy_events');
        Schema::dropIfExists('travel_approval_policies');
    }
};
