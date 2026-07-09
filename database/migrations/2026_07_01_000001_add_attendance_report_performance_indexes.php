<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_results', function (Blueprint $table) {
            $table->index(
                ['attendance_import_id', 'attendance_date', 'employee_name'],
                'attendance_results_import_date_name_idx',
            );
            $table->index(
                ['attendance_import_id', 'location_check'],
                'attendance_results_import_location_check_idx',
            );
        });

        Schema::table('work_hour_records', function (Blueprint $table) {
            $table->index(
                ['attendance_import_id', 'work_date', 'employee_name'],
                'work_hour_records_import_date_name_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('attendance_results', function (Blueprint $table) {
            $table->dropIndex('attendance_results_import_date_name_idx');
            $table->dropIndex('attendance_results_import_location_check_idx');
        });

        Schema::table('work_hour_records', function (Blueprint $table) {
            $table->dropIndex('work_hour_records_import_date_name_idx');
        });
    }
};
