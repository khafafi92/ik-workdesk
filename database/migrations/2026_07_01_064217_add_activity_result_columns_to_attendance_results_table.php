<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_results', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_results', 'employee_code')) {
                $table->string('employee_code')->nullable()->after('attendance_import_id');
            }

            if (! Schema::hasColumn('attendance_results', 'check_time')) {
                $table->time('check_time')->nullable()->after('attendance_date');
            }

            if (! Schema::hasColumn('attendance_results', 'check_type')) {
                $table->string('check_type')->nullable()->after('check_time');
            }

            if (! Schema::hasColumn('attendance_results', 'job_position')) {
                $table->string('job_position')->nullable()->after('employee_name');
            }

            if (! Schema::hasColumn('attendance_results', 'shift_name')) {
                $table->string('shift_name')->nullable()->after('job_position');
            }

            if (! Schema::hasColumn('attendance_results', 'location_setting_name')) {
                $table->string('location_setting_name')->nullable()->after('shift_name');
            }

            if (! Schema::hasColumn('attendance_results', 'location_coordinate')) {
                $table->string('location_coordinate')->nullable()->after('location_gps_name');
            }

            if (! Schema::hasColumn('attendance_results', 'description')) {
                $table->text('description')->nullable()->after('location_coordinate');
            }

            if (! Schema::hasColumn('attendance_results', 'mobile_flag')) {
                $table->string('mobile_flag')->nullable()->after('description');
            }

            if (! Schema::hasColumn('attendance_results', 'approval_status')) {
                $table->string('approval_status')->nullable()->after('mobile_flag');
            }

            if (! Schema::hasColumn('attendance_results', 'location_check')) {
                $table->string('location_check')->nullable()->after('approval_status');
            }

            if (! Schema::hasColumn('attendance_results', 'duration_minutes')) {
                $table->integer('duration_minutes')->default(0)->after('location_check');
            }

            if (! Schema::hasColumn('attendance_results', 'duration_text')) {
                $table->string('duration_text')->nullable()->after('duration_minutes');
            }

            if (! Schema::hasColumn('attendance_results', 'checkout_check')) {
                $table->string('checkout_check')->nullable()->after('duration_text');
            }

            if (! Schema::hasColumn('attendance_results', 'matched_location_name')) {
                $table->string('matched_location_name')->nullable()->after('checkout_check');
            }

            if (! Schema::hasColumn('attendance_results', 'distance_meters')) {
                $table->decimal('distance_meters', 10, 2)->nullable()->after('matched_location_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_results', function (Blueprint $table) {
            $columns = [
                'employee_code',
                'check_time',
                'check_type',
                'job_position',
                'shift_name',
                'location_setting_name',
                'location_coordinate',
                'description',
                'mobile_flag',
                'approval_status',
                'location_check',
                'duration_minutes',
                'duration_text',
                'checkout_check',
                'matched_location_name',
                'distance_meters',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('attendance_results', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};