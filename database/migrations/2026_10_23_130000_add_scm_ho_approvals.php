<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scm_approval_routes', function (Blueprint $table): void {
            $table->id();
            $table->string('company', 10);
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('acknowledger_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('cbo_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('cbo3_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->unique(['company', 'department_id']);
            $table->timestamps();
        });
        Schema::table('scm_requests', function (Blueprint $table): void {
            $table->string('approval_scope', 10)->nullable();
            $table->unsignedBigInteger('estimated_total_minor')->nullable();
            $table->unsignedInteger('approval_round')->default(0);
        });
        Schema::create('scm_request_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('scm_requests')->restrictOnDelete();
            $table->unsignedInteger('round');
            $table->unsignedInteger('position');
            $table->string('role', 30);
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->boolean('is_required');
            $table->string('status', 20)->default('pending');
            $table->foreignId('signature_id')->nullable()->constrained('scm_user_signatures')->restrictOnDelete();
            $table->string('signed_name')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['request_id', 'round', 'role']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        if (DB::table('scm_request_approvals')->exists() || DB::table('scm_approval_routes')->exists()) {
            throw new RuntimeException('Migrasi persetujuan HO tidak dapat dibatalkan setelah digunakan.');
        }
        Schema::dropIfExists('scm_request_approvals');
        Schema::table('scm_requests', function (Blueprint $table): void {
            $table->dropColumn(['approval_scope', 'estimated_total_minor', 'approval_round']);
        });
        Schema::dropIfExists('scm_approval_routes');
    }
};
