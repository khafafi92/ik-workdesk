<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 3);
            $table->string('company', 10);
            $table->string('number', 190);
            $table->string('number_key', 190);
            $table->string('revision', 30)->default('-');
            $table->date('issued_on');
            $table->date('applied_on');
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('spd_id')->nullable()->constrained('travel_documents')->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('approval_round')->default(0);
            $table->json('data');
            $table->json('employee_snapshot');
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedBigInteger('advance_one_minor')->default(0);
            $table->unsignedBigInteger('advance_two_minor')->default(0);
            $table->bigInteger('balance_minor')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->json('prepared_signature')->nullable();
            $table->timestamps();
            $table->unique(['kind', 'company', 'number_key']);
            $table->unique('spd_id');
            $table->index(['kind', 'status', 'department_id']);
            $table->index(['created_by', 'kind']);
        });
        Schema::create('travel_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('travel_document_id')->constrained('travel_documents')->restrictOnDelete();
            $table->unsignedInteger('round');
            $table->unsignedTinyInteger('step');
            $table->string('role', 30);
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('assigned_name');
            $table->string('assigned_position')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('decided_at')->nullable();
            $table->text('reason')->nullable();
            $table->json('signature')->nullable();
            $table->timestamps();
            $table->unique(['travel_document_id', 'round', 'step']);
            $table->index(['user_id', 'status']);
        });
        Schema::create('travel_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('travel_document_id')->constrained('travel_documents')->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('path')->unique();
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
        Schema::create('travel_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('travel_document_id')->constrained('travel_documents')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 40);
            $table->json('details');
            $table->timestamp('created_at');
        });
        foreach (['travel.spd.create' => 'Buat SPD sendiri', 'travel.spd.view' => 'Lihat SPD departemen',
            'travel.spd.manage' => 'Kelola draft SPD departemen', 'travel.lpj.create' => 'Buat LPJ sendiri',
            'travel.lpj.view' => 'Lihat LPJ departemen', 'travel.lpj.manage' => 'Kelola draft LPJ departemen',
            'travel.approve' => 'Persetujuan SPD dan LPJ yang ditugaskan'] as $code => $name) {
            DB::table('permissions')->insertOrIgnore(['code' => $code, 'name' => $name,
                'module' => 'Perjalanan Dinas', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (DB::table('travel_documents')->exists()) {
            throw new RuntimeException('Pertahankan dokumen SPD dan LPJ yang sudah tersimpan.');
        }
        foreach (['travel_events', 'travel_files', 'travel_approvals', 'travel_documents'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
