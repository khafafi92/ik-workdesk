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
            $table->string('need_type', 10)->default('HO');
        });
        Schema::create('scm_approval_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('company', 10);
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('need_type', 10);
            $table->string('request_kind', 3)->default('ALL');
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('acknowledger_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('crosschecker_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approver_id')->constrained('users')->restrictOnDelete();
            $table->boolean('use_amount_escalation')->default(false);
            $table->foreignId('high_approver_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('crosscheck_mode', 20)->default('always');
            $table->boolean('uses_legacy_routing')->default(false);
            $table->foreignId('legacy_route_id')->nullable()->unique()->constrained('scm_approval_routes')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['company', 'department_id', 'need_type', 'request_kind'], 'scm_policy_scope_unique');
        });
        foreach (DB::table('scm_approval_routes')->get() as $route) {
            DB::table('scm_approval_policies')->insert([
                'company' => $route->company, 'department_id' => $route->department_id, 'need_type' => 'HO', 'request_kind' => 'ALL',
                'acknowledger_id' => $route->acknowledger_id, 'crosschecker_id' => $route->cbo_id, 'approver_id' => $route->cbo_id,
                'use_amount_escalation' => true, 'high_approver_id' => $route->cbo3_id, 'crosscheck_mode' => 'above_limit',
                'uses_legacy_routing' => false, 'legacy_route_id' => $route->id, 'version' => $route->version,
                'created_at' => $route->created_at, 'updated_at' => $route->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        if (DB::table('scm_approval_policies')->whereNull('legacy_route_id')->exists()
            || DB::table('scm_requests')->where('need_type', 'PROJECT')->exists()) {
            throw new RuntimeException('Master dan pengajuan Project yang sudah digunakan harus dipertahankan.');
        }
        Schema::dropIfExists('scm_approval_policies');
        Schema::table('scm_requests', fn (Blueprint $table) => $table->dropColumn('need_type'));
    }
};
