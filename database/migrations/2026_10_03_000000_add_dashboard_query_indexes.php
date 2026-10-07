<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->index(['status', 'due_at']);
            $table->index('created_at');
        });

        Schema::table('work_tasks', function (Blueprint $table): void {
            $table->index(['status', 'due_at']);
            $table->index('created_at');
        });

        Schema::table('reminders', function (Blueprint $table): void {
            $table->index(['status', 'reminder_at']);
        });

        Schema::table('attendance_results', function (Blueprint $table): void {
            $table->index('attendance_date');
        });

        Schema::table('atk_requests', function (Blueprint $table): void {
            $table->index(['status', 'completed_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('atk_requests', function (Blueprint $table): void {
            $table->dropIndex(['status', 'completed_at']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('attendance_results', function (Blueprint $table): void {
            $table->dropIndex(['attendance_date']);
        });

        Schema::table('reminders', function (Blueprint $table): void {
            $table->dropIndex(['status', 'reminder_at']);
        });

        Schema::table('work_tasks', function (Blueprint $table): void {
            $table->dropIndex(['status', 'due_at']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropIndex(['status', 'due_at']);
            $table->dropIndex(['created_at']);
        });
    }
};
