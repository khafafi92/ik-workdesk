<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_request_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('company', 5);
            $table->string('kind', 20);
            $table->json('reviewers');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['company', 'kind']);
        });

        Schema::create('finance_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 20);
            $table->string('company', 5);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('travel_document_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('issued_on');
            $table->string('invoice_reference', 80)->nullable();
            $table->string('status', 15)->default('draft');
            $table->json('data');
            $table->unsignedBigInteger('total_minor');
            $table->unsignedInteger('round')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['company', 'invoice_reference']);
        });

        Schema::create('finance_request_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('finance_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('round');
            $table->unsignedTinyInteger('step');
            $table->string('label');
            $table->string('reviewer_name');
            $table->string('status', 15)->default('pending');
            $table->text('note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->unique(['finance_request_id', 'round', 'step']);
            $table->index(['user_id', 'finance_request_id']);
        });

        Schema::create('finance_request_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('finance_request_id')->constrained()->cascadeOnDelete();
            $table->string('path')->unique();
            $table->string('original_name');
            $table->string('mime');
            $table->timestamps();
        });

        DB::table('permissions')->insertOrIgnore([
            'code' => 'finance.requests.create', 'name' => 'Buat Payment Request dan Reimbursement',
            'module' => 'Finance', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (DB::table('finance_requests')->exists() || DB::table('finance_request_policies')->exists()) {
            throw new RuntimeException('Pertahankan pengajuan pembayaran dan aturan approval yang sudah tersimpan.');
        }
        Schema::dropIfExists('finance_request_files');
        Schema::dropIfExists('finance_request_approvals');
        Schema::dropIfExists('finance_requests');
        Schema::dropIfExists('finance_request_policies');
        DB::table('permissions')->where('code', 'finance.requests.create')->delete();
    }
};
