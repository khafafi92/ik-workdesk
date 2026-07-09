<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_results', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_results', 'location_address')) {
                $table->text('location_address')->nullable()->after('location_gps_name');
            }
        });

        Schema::table('work_hour_records', function (Blueprint $table) {
            if (! Schema::hasColumn('work_hour_records', 'employee_code')) {
                $table->string('employee_code')->nullable()->after('attendance_import_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_results', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_results', 'location_address')) {
                $table->dropColumn('location_address');
            }
        });

        Schema::table('work_hour_records', function (Blueprint $table) {
            if (Schema::hasColumn('work_hour_records', 'employee_code')) {
                $table->dropColumn('employee_code');
            }
        });
    }
};