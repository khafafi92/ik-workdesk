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
    Schema::create('attendance_imports', function (Blueprint $table) {
        $table->id();

        $table->foreignId('uploaded_by_user_id')
            ->nullable()
            ->constrained('users')
            ->nullOnDelete();

        $table->string('period_name')->nullable();

        $table->string('attendance_file_name')->nullable();
        $table->string('attendance_file_path')->nullable();

        $table->string('work_hour_file_name')->nullable();
        $table->string('work_hour_file_path')->nullable();

        $table->string('status')->default('draft');
        $table->text('notes')->nullable();

        $table->timestamp('processed_at')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_imports');
    }
};
