<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ltro_daily_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('unit_code', 20);
            $table->date('report_date');
            $table->string('operator_day')->nullable();
            $table->string('operator_night')->nullable();
            $table->json('engine_values');
            $table->json('compressor_values');
            $table->decimal('engine_average', 12, 2)->nullable();
            $table->decimal('compressor_average', 12, 2)->nullable();
            $table->decimal('combined_average', 12, 2)->nullable();
            $table->decimal('running_hours', 12, 2)->nullable();
            $table->decimal('standby_hours', 12, 2)->nullable();
            $table->decimal('down_reactive_hours', 12, 2)->nullable();
            $table->text('shutdown_indication')->nullable();
            $table->decimal('last_stock_oil', 12, 2)->nullable();
            $table->decimal('received_oil', 12, 2)->nullable();
            $table->decimal('used_oil', 12, 2)->nullable();
            $table->text('remark_used_oil')->nullable();
            $table->json('average_notes')->nullable();
            $table->text('activity')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_code', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ltro_daily_reports');
    }
};
