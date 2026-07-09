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
    Schema::create('tickets', function (Blueprint $table) {
        $table->id();

        $table->string('ticket_no')->unique();

        $table->foreignId('employee_id')
            ->nullable()
            ->constrained()
            ->nullOnDelete();

        $table->foreignId('requester_department_id')
            ->nullable()
            ->constrained('departments')
            ->nullOnDelete();

        $table->foreignId('handler_department_id')
            ->nullable()
            ->constrained('departments')
            ->nullOnDelete();

        $table->foreignId('ticket_category_id')
            ->nullable()
            ->constrained()
            ->nullOnDelete();

        $table->string('subject');
        $table->text('description')->nullable();

        $table->string('priority')->default('medium');
        $table->string('status')->default('open');

        $table->string('assigned_to')->nullable();
        $table->timestamp('reported_at')->nullable();
        $table->timestamp('due_at')->nullable();
        $table->timestamp('resolved_at')->nullable();

        $table->text('resolution_notes')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
