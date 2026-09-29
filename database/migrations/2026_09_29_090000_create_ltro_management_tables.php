<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ltro_units', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ltro_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ltro_mttr_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('sequence_no')->nullable();
            $table->foreignId('ltro_unit_id')->constrained('ltro_units')->restrictOnDelete();
            $table->foreignId('ltro_category_id')->nullable()->constrained('ltro_categories')->nullOnDelete();
            $table->string('rental_period')->nullable();
            $table->date('shutdown_month')->nullable()->index();
            $table->dateTime('shutdown_datetime')->index();
            $table->dateTime('running_datetime')->nullable();
            $table->unsignedInteger('downtime_minutes')->default(0);
            $table->decimal('running_hours', 10, 2)->nullable();
            $table->decimal('pk_100', 10, 2)->nullable();
            $table->text('indication')->nullable();
            $table->text('immediate_cause')->nullable();
            $table->text('activity_troubleshooting')->nullable();
            $table->string('source_file')->nullable();
            $table->unsignedInteger('source_row')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['ltro_unit_id', 'shutdown_datetime']);
            $table->unique(['source_file', 'source_row']);
        });

        Schema::create('ltro_availability_records', function (Blueprint $table): void {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedSmallInteger('row_no');
            $table->string('day_name');
            $table->date('report_date');
            $table->decimal('flow_rate', 10, 2)->nullable();
            $table->string('rental_code', 20)->nullable();
            $table->decimal('fuel_gas_consumption', 12, 4)->nullable();
            $table->decimal('spare_part_availability', 8, 2)->nullable();
            $table->decimal('comp_a_unplanned_shutdown_hours', 8, 2)->nullable();
            $table->decimal('comp_a_shutdown_hours', 8, 2)->nullable();
            $table->decimal('comp_a_running_hours', 8, 2)->nullable();
            $table->decimal('comp_b_unplanned_shutdown_hours', 8, 2)->nullable();
            $table->decimal('comp_b_shutdown_hours', 8, 2)->nullable();
            $table->decimal('comp_b_running_hours', 8, 2)->nullable();
            $table->decimal('comp_c_unplanned_shutdown_hours', 8, 2)->nullable();
            $table->decimal('comp_c_shutdown_hours', 8, 2)->nullable();
            $table->decimal('comp_c_running_hours', 8, 2)->nullable();
            $table->decimal('total_required_hours', 8, 2)->nullable();
            $table->decimal('availability_system_capacity', 10, 2)->nullable();
            $table->decimal('lpo', 10, 2)->nullable();
            $table->decimal('reliability_percent', 8, 2)->nullable();
            $table->decimal('availability_percent', 8, 2)->nullable();
            $table->decimal('doe_percent', 8, 2)->nullable();
            $table->text('remark')->nullable();
            $table->decimal('pk100_shutdown_hours', 8, 2)->nullable();
            $table->decimal('ltrx_a_running_hours', 8, 2)->nullable();
            $table->decimal('ltrx_b_running_hours', 8, 2)->nullable();
            $table->decimal('pk101_running_hours', 8, 2)->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['period_start', 'report_date']);
        });

        DB::table('ltro_units')->insert([
            ['code' => 'A', 'name' => 'Unit-A', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'B', 'name' => 'Unit-B', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'C', 'name' => 'Unit-C', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('ltro_categories')->insert([
            ['name' => 'Plan', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Plan MEPG', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Unplan', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Unplan MEPG', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ltro_availability_records');
        Schema::dropIfExists('ltro_mttr_records');
        Schema::dropIfExists('ltro_categories');
        Schema::dropIfExists('ltro_units');
    }
};
