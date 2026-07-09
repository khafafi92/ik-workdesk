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
    Schema::create('reminders', function (Blueprint $table) {
        $table->id();

        $table->foreignId('employee_id')
            ->nullable()
            ->constrained('employees')
            ->nullOnDelete();

        $table->foreignId('department_id')
            ->nullable()
            ->constrained('departments')
            ->nullOnDelete();

        $table->string('reminder_type')->default('general');
        $table->string('title');
        $table->text('description')->nullable();

        $table->timestamp('reminder_at');
        $table->string('status')->default('pending');

        $table->boolean('is_notified')->default(false);
        $table->timestamp('notified_at')->nullable();

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
