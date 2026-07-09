<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Attendance Imports / Upload Period
        |--------------------------------------------------------------------------
        */

        if (Schema::hasTable('attendance_imports')) {
            if (
                Schema::hasColumn('attendance_imports', 'status')
                && Schema::hasColumn('attendance_imports', 'created_at')
            ) {
                DB::statement(
                    'CREATE INDEX IF NOT EXISTS
                    idx_attendance_imports_status_created
                    ON attendance_imports (status, created_at DESC)'
                );
            }

            if (
                Schema::hasColumn(
                    'attendance_imports',
                    'uploaded_by_user_id'
                )
                && Schema::hasColumn(
                    'attendance_imports',
                    'created_at'
                )
            ) {
                DB::statement(
                    'CREATE INDEX IF NOT EXISTS
                    idx_attendance_imports_user_created
                    ON attendance_imports
                    (uploaded_by_user_id, created_at DESC)'
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Raw Attendance Records
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasTable('attendance_records')
            && Schema::hasColumn(
                'attendance_records',
                'attendance_import_id'
            )
            && Schema::hasColumn(
                'attendance_records',
                'attendance_date'
            )
            && Schema::hasColumn(
                'attendance_records',
                'employee_name'
            )
        ) {
            DB::statement(
                'CREATE INDEX IF NOT EXISTS
                idx_attendance_records_import_date_name
                ON attendance_records
                (attendance_import_id, attendance_date, employee_name)'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Attendance Results
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasTable('attendance_results')
            && Schema::hasColumn(
                'attendance_results',
                'attendance_import_id'
            )
            && Schema::hasColumn(
                'attendance_results',
                'final_status'
            )
        ) {
            DB::statement(
                'CREATE INDEX IF NOT EXISTS
                idx_attendance_results_import_final_status
                ON attendance_results
                (attendance_import_id, final_status)'
            );
        }

        if (
            Schema::hasTable('attendance_results')
            && Schema::hasColumn(
                'attendance_results',
                'employee_code'
            )
            && Schema::hasColumn(
                'attendance_results',
                'attendance_date'
            )
        ) {
            DB::statement(
                'CREATE INDEX IF NOT EXISTS
                idx_attendance_results_employee_code_date
                ON attendance_results
                (employee_code, attendance_date DESC)'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Work Hour Records
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasTable('work_hour_records')
            && Schema::hasColumn(
                'work_hour_records',
                'employee_code'
            )
            && Schema::hasColumn(
                'work_hour_records',
                'work_date'
            )
        ) {
            DB::statement(
                'CREATE INDEX IF NOT EXISTS
                idx_work_hour_records_employee_code_date
                ON work_hour_records
                (employee_code, work_date DESC)'
            );
        }
    }

    public function down(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS
            idx_attendance_imports_status_created'
        );

        DB::statement(
            'DROP INDEX IF EXISTS
            idx_attendance_imports_user_created'
        );

        DB::statement(
            'DROP INDEX IF EXISTS
            idx_attendance_records_import_date_name'
        );

        DB::statement(
            'DROP INDEX IF EXISTS
            idx_attendance_results_import_final_status'
        );

        DB::statement(
            'DROP INDEX IF EXISTS
            idx_attendance_results_employee_code_date'
        );

        DB::statement(
            'DROP INDEX IF EXISTS
            idx_work_hour_records_employee_code_date'
        );
    }
};
