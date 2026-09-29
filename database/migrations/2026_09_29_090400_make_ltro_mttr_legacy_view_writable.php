<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ltro_mttr_records', function ($table): void {
            $table->string('category')->nullable()->after('ltro_category_id');
        });

        DB::table('ltro_mttr_records')
            ->orderBy('id')
            ->each(function (object $record): void {
                $category = $record->ltro_category_id
                    ? DB::table('ltro_categories')->where('id', $record->ltro_category_id)->value('name')
                    : null;

                DB::table('ltro_mttr_records')->where('id', $record->id)->update(['category' => $category]);
            });

        DB::statement('DROP VIEW mttr_records');
        DB::statement('CREATE VIEW mttr_records AS
            SELECT id, sequence_no, ltro_unit_id AS unit_id, ltro_category_id AS category_id, category,
                rental_period, shutdown_month, shutdown_datetime, running_datetime, downtime_minutes,
                running_hours, pk_100, indication, immediate_cause, activity_troubleshooting,
                source_file, source_row, created_at, updated_at
            FROM ltro_mttr_records');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW mttr_records');
        DB::statement('CREATE VIEW mttr_records AS
            SELECT records.id, records.sequence_no, records.ltro_unit_id AS unit_id,
                records.ltro_category_id AS category_id, categories.name AS category, records.rental_period,
                records.shutdown_month, records.shutdown_datetime, records.running_datetime,
                records.downtime_minutes, records.running_hours, records.pk_100, records.indication,
                records.immediate_cause, records.activity_troubleshooting, records.source_file,
                records.source_row, records.created_at, records.updated_at
            FROM ltro_mttr_records records
            LEFT JOIN ltro_categories categories ON categories.id = records.ltro_category_id');
        Schema::table('ltro_mttr_records', function ($table): void {
            $table->dropColumn('category');
        });
    }
};
