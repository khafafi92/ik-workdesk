<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_assignments', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('ticket_id')
                ->constrained('tickets')
                ->cascadeOnDelete();

            $table->foreignId('department_id')
                ->constrained('departments')
                ->cascadeOnDelete();

            $table->foreignId('work_task_id')
                ->nullable()
                ->constrained('work_tasks')
                ->nullOnDelete();

            $table->boolean('is_required')
                ->default(true);

            $table->unsignedSmallInteger('sort_order')
                ->default(0);

            $table->text('notes')
                ->nullable();

            $table->timestamps();

            $table->unique(
                ['ticket_id', 'department_id'],
                'ticket_assignment_department_unique'
            );

            $table->unique(
                'work_task_id',
                'ticket_assignment_work_task_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_assignments');
    }
};