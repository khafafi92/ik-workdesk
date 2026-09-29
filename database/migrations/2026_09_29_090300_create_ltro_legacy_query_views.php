<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE VIEW units AS SELECT id, code AS unit_code, name AS unit_name, is_active, created_at, updated_at FROM ltro_units');
        DB::statement('CREATE VIEW categories AS SELECT id, name AS category_name, is_active, created_at, updated_at FROM ltro_categories');
        DB::statement('CREATE VIEW mttr_records AS
            SELECT records.id, records.sequence_no, records.ltro_unit_id AS unit_id,
                records.ltro_category_id AS category_id, records.rental_period, records.shutdown_month,
                records.shutdown_datetime, records.running_datetime, records.downtime_minutes,
                categories.name AS category, records.indication, records.immediate_cause,
                records.activity_troubleshooting, records.running_hours, records.pk_100,
                records.source_file, records.source_row, records.created_at, records.updated_at
            FROM ltro_mttr_records records
            LEFT JOIN ltro_categories categories ON categories.id = records.ltro_category_id');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS mttr_records');
        DB::statement('DROP VIEW IF EXISTS categories');
        DB::statement('DROP VIEW IF EXISTS units');
    }
};
