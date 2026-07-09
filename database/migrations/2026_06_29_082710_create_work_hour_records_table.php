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
    Schema::create('work_hour_records', function (Blueprint $table) {
        $table->id();

        $table->foreignId('attendance_import_id')
            ->nullable()
            ->constrained('attendance_imports')
            ->cascadeOnDelete();

        $table->string('employee_name');
        $table->date('work_date')->nullable();

        $table->integer('work_minutes')->default(0);
        $table->string('work_hours_text')->nullable();

        $table->jsonb('raw_data')->nullable();

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_hour_records');
    }
};
