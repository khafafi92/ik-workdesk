<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_results', function (Blueprint $table) {
            $table->string('employee_id')->nullable()->after('attendance_import_id');
            $table->string('job_position')->nullable()->after('employee_name');
            $table->string('shift_name')->nullable()->after('job_position');
            $table->string('location_check')->nullable()->after('location_status');
            $table->string('time_check')->nullable()->after('location_check');
            $table->string('checkout_check')->nullable()->after('time_check');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_results', function (Blueprint $table) {
            $table->dropColumn([
                'employee_id',
                'job_position',
                'shift_name',
                'location_check',
                'time_check',
                'checkout_check',
            ]);
        });
    }
};
