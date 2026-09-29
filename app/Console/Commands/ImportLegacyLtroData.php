<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\LtroAsset;
use App\Models\LtroAvailabilityRecord;
use App\Models\LtroCategory;
use App\Models\LtroDailyReport;
use App\Models\LtroMttrRecord;
use App\Models\LtroUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyLtroData extends Command
{
    protected $signature = 'ltro:import-legacy-data {--database=ltrom : Legacy PostgreSQL database name}';

    protected $description = 'Copy LTRO master, MTTR, and availability data from the legacy LTRO database.';

    public function handle(): int
    {
        config([
            'database.connections.ltro_legacy' => array_merge(
                config('database.connections.pgsql'),
                ['database' => $this->option('database')],
            ),
        ]);

        $legacy = DB::connection('ltro_legacy');

        if (! $legacy->getSchemaBuilder()->hasTable('mttr_records')) {
            $this->error('Legacy LTRO database does not contain mttr_records.');

            return self::FAILURE;
        }

        $unitMap = $legacy->table('units')->get()->mapWithKeys(function (object $unit): array {
            $target = LtroUnit::query()->updateOrCreate(
                ['code' => $unit->unit_code ?: (string) $unit->id],
                ['name' => $unit->unit_name, 'is_active' => (bool) $unit->is_active],
            );

            return [$unit->id => $target->id];
        });

        $categoryMap = $legacy->table('categories')->get()->mapWithKeys(function (object $category): array {
            $target = LtroCategory::query()->updateOrCreate(
                ['name' => $category->category_name],
                ['is_active' => (bool) $category->is_active],
            );

            return [$category->id => $target->id];
        });

        $assetCount = 0;

        if ($legacy->getSchemaBuilder()->hasTable('assets')) {
            $legacy->table('assets')->orderBy('id')->each(function (object $asset) use ($unitMap, &$assetCount): void {
                $unitId = $unitMap->get($asset->unit_id);

                if (! $unitId) {
                    return;
                }

                LtroAsset::query()->updateOrCreate(
                    ['name' => $asset->asset_name, 'ltro_unit_id' => $unitId],
                    ['is_active' => (bool) $asset->is_active],
                );
                $assetCount++;
            });
        }

        $mttrCount = 0;
        $legacy->table('mttr_records')->orderBy('id')->chunkById(100, function ($records) use ($unitMap, $categoryMap, &$mttrCount): void {
            foreach ($records as $record) {
                $unitId = $unitMap->get($record->unit_id);

                if (! $unitId) {
                    $this->warn("Skipping legacy MTTR #{$record->id}: unit is unavailable.");

                    continue;
                }

                $values = [
                    'sequence_no' => $record->sequence_no,
                    'ltro_unit_id' => $unitId,
                    'ltro_category_id' => $categoryMap->get($record->category_id),
                    'rental_period' => $record->rental_period,
                    'shutdown_month' => $record->shutdown_month,
                    'shutdown_datetime' => $record->shutdown_datetime,
                    'running_datetime' => $record->running_datetime,
                    'downtime_minutes' => $record->downtime_minutes,
                    'running_hours' => $record->running_hours,
                    'pk_100' => $record->pk_100 ?? null,
                    'indication' => $record->indication,
                    'immediate_cause' => $record->immediate_cause,
                    'activity_troubleshooting' => $record->activity_troubleshooting,
                    'source_file' => $record->source_file,
                    'source_row' => $record->source_row,
                ];

                LtroMttrRecord::query()->updateOrCreate(
                    ['source_file' => $record->source_file, 'source_row' => $record->source_row],
                    $values,
                );
                $mttrCount++;
            }
        });

        $availabilityCount = 0;

        if ($legacy->getSchemaBuilder()->hasTable('availability_ltro_1b_records')) {
            $legacy->table('availability_ltro_1b_records')->orderBy('id')->chunkById(100, function ($records) use (&$availabilityCount): void {
                foreach ($records as $record) {
                    $values = (array) $record;
                    unset($values['id'], $values['created_at'], $values['updated_at']);
                    $values['updated_by_user_id'] = null;

                    LtroAvailabilityRecord::query()->updateOrCreate(
                        ['period_start' => $record->period_start, 'report_date' => $record->report_date],
                        $values,
                    );
                    $availabilityCount++;
                }
            });
        }

        $dailyReportCount = 0;

        if ($legacy->getSchemaBuilder()->hasTable('daily_reports')) {
            $legacy->table('daily_reports')->orderBy('id')->chunkById(100, function ($reports) use (&$dailyReportCount): void {
                foreach ($reports as $report) {
                    $values = (array) $report;
                    unset($values['id'], $values['created_at'], $values['updated_at']);
                    $values['created_by_user_id'] = null;
                    $values['updated_by_user_id'] = null;

                    LtroDailyReport::query()->updateOrCreate(
                        ['unit_code' => $report->unit_code, 'report_date' => $report->report_date],
                        $values,
                    );
                    $dailyReportCount++;
                }
            });
        }

        $activityLogCount = 0;

        if ($legacy->getSchemaBuilder()->hasTable('activity_logs')) {
            $legacy->table('activity_logs')->orderBy('id')->each(function (object $log) use (&$activityLogCount): void {
                ActivityLog::query()->updateOrCreate(
                    [
                        'action' => $log->action,
                        'module' => $log->module,
                        'description' => $log->description,
                        'created_at' => $log->created_at,
                    ],
                    [
                        'user_id' => null,
                        'user_name' => $log->user_name,
                        'ip_address' => $log->ip_address,
                        'user_agent' => $log->user_agent,
                    ],
                );
                $activityLogCount++;
            });
        }

        $this->info("Imported {$assetCount} assets, {$mttrCount} MTTR records, {$availabilityCount} availability rows, {$dailyReportCount} daily reports, and {$activityLogCount} activity logs.");

        return self::SUCCESS;
    }
}
