<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table): void {
            $table->foreignId('work_task_id')
                ->nullable()
                ->after('department_id')
                ->constrained('work_tasks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('work_task_id');
        });
    }
};
