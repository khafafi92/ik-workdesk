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
    Schema::create('attendance_results', function (Blueprint $table) {
        $table->id();

        $table->foreignId('attendance_import_id')
            ->nullable()
            ->constrained('attendance_imports')
            ->cascadeOnDelete();

        $table->string('employee_name');
        $table->date('attendance_date')->nullable();

        $table->time('clock_in')->nullable();
        $table->time('clock_out')->nullable();

        $table->integer('work_minutes')->default(0);

        $table->string('location_gps_name')->nullable();
        $table->decimal('distance_meters', 10, 2)->nullable();

        $table->time('expected_checkout')->nullable();

        $table->string('clock_in_status')->nullable();
        $table->string('location_status')->nullable();
        $table->string('checkout_status')->nullable();
        $table->string('work_hour_status')->nullable();
        $table->string('final_status')->nullable();

        $table->text('notes')->nullable();

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_results');
    }
};
