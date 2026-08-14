<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_projects', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('active');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('work_tasks', function (Blueprint $table): void {
            $table->foreignId('work_project_id')->nullable()->after('task_category_id')
                ->constrained('work_projects')->nullOnDelete();
        });

        Schema::create('daily_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_task_id')->nullable()->constrained('work_tasks')->nullOnDelete();
            $table->foreignId('work_project_id')->nullable()->constrained('work_projects')->nullOnDelete();
            $table->foreignId('activity_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requester_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('requester_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('work_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('result')->nullable();
            $table->unsignedInteger('duration_minutes');
            $table->string('work_context');
            $table->string('source_type')->default('manual');
            $table->string('requester_type');
            $table->string('requester_company_name')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'work_date']);
            $table->index(['work_context', 'work_date']);
            $table->index(['requester_type', 'work_date']);
        });

        DB::table('activity_categories')->insert([
            ['code' => 'ADMIN', 'name' => 'Administrasi', 'description' => 'Administrasi dan dokumentasi internal.', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'MEETING', 'name' => 'Meeting & Koordinasi', 'description' => 'Rapat, briefing, dan koordinasi.', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'SUPPORT', 'name' => 'Support Internal', 'description' => 'Dukungan operasional kepada user atau divisi lain.', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'ROUTINE', 'name' => 'Pekerjaan Rutin', 'description' => 'Pekerjaan operasional yang berulang.', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'LEARNING', 'name' => 'Learning & Improvement', 'description' => 'Pelatihan, riset, dan peningkatan proses.', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_activities');
        Schema::table('work_tasks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('work_project_id');
        });
        Schema::dropIfExists('activity_categories');
        Schema::dropIfExists('work_projects');
    }
};
