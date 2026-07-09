<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('work_tasks', function (Blueprint $table) {
            $table->id();

            $table->string('task_no')->unique();

            $table->foreignId('ticket_id')
                ->nullable()
                ->constrained('tickets')
                ->nullOnDelete();

            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete();

            $table->foreignId('employee_id')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();

            $table->foreignId('task_category_id')
                ->nullable()
                ->constrained('task_categories')
                ->nullOnDelete();

            $table->string('work_scope')->default('office');
            $table->string('title');
            $table->text('description')->nullable();

            $table->string('priority')->default('medium');
            $table->string('status')->default('planned');

            $table->unsignedTinyInteger('progress_percent')->default(0);

            $table->timestamp('start_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_tasks');
    }
};
