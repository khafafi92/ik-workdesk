<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scm_cbe_approval_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('company')->unique();
            $table->foreignId('acknowledger_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('crosschecker_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approver_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
        Schema::create('scm_cbe_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cbe_id')->constrained('scm_cbes')->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->string('role');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('assigned_name');
            $table->string('status')->default('pending');
            $table->foreignId('signature_id')->nullable()->constrained('scm_user_signatures')->restrictOnDelete();
            $table->string('signed_name')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();
            $table->unique(['cbe_id', 'position']);
            $table->unique(['cbe_id', 'role']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scm_cbe_approvals');
        Schema::dropIfExists('scm_cbe_approval_policies');
    }
};
