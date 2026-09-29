<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\LtroCategory;
use App\Models\LtroMttrRecord;
use App\Models\LtroUnit;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class LtroMttrImportController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $file = $request->validate([
            'mttr_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ])['mttr_file'];
        $sourceFile = $file->getClientOriginalName();
        $rows = IOFactory::load($file->getRealPath())->getActiveSheet()->toArray(null, true, false, true);

        if (count($rows) < 2) {
            return back()->withErrors(['mttr_file' => 'File Excel tidak memiliki data.']);
        }

        [$headerRow, $headers] = $this->headers($rows);
        $processed = $created = $updated = 0;
        $notes = [];

        foreach (array_slice($rows, $headerRow, null, true) as $rowNumber => $row) {
            if (collect($row)->filter(fn ($value) => $value !== null && $value !== '')->isEmpty()) {
                continue;
            }

            try {
                $value = fn (string $key) => $row[$headers[$key] ?? ''] ?? null;
                $unitName = $this->text($value('unit'));
                $shutdownDate = $this->date($value('dateshutdown'));

                if (! $unitName || ! $shutdownDate) {
                    $notes[] = "Baris {$rowNumber}: Unit atau Date Shutdown kosong.";

                    continue;
                }

                $unit = LtroUnit::query()->updateOrCreate(
                    ['code' => str_replace('Unit-', '', $unitName)],
                    ['name' => $unitName, 'is_active' => true],
                );
                $categoryName = $this->text($value('category'));
                $category = $categoryName
                    ? LtroCategory::query()->updateOrCreate(['name' => $categoryName], ['is_active' => true])
                    : null;
                $downtime = (int) ($value('downtimeminute') ?? 0);
                $shutdown = Carbon::parse($shutdownDate->toDateString().' '.($this->time($value('timeshutdown')) ?? '00:00:00'));
                $runningDate = $this->date($value('daterunning'));
                $runningTime = $this->time($value('timerunning'));
                $running = $runningDate && $runningTime
                    ? Carbon::parse($runningDate->toDateString().' '.$runningTime)
                    : $shutdown->copy()->addMinutes($downtime);

                $values = [
                    'sequence_no' => is_numeric($value('no')) ? (int) $value('no') : null,
                    'ltro_unit_id' => $unit->id,
                    'ltro_category_id' => $category?->id,
                    'category' => $category?->name,
                    'rental_period' => $this->text($value('rentalperiod')),
                    'shutdown_month' => $this->date($value('month'))?->startOfMonth()->toDateString(),
                    'shutdown_datetime' => $shutdown,
                    'running_datetime' => $running,
                    'downtime_minutes' => $downtime,
                    'running_hours' => is_numeric($value('rh')) ? (float) $value('rh') : null,
                    'pk_100' => is_numeric($value('pk100')) ? (float) $value('pk100') : null,
                    'indication' => $this->text($value('indication')),
                    'immediate_cause' => $this->text($value('immediatecause')),
                    'activity_troubleshooting' => $this->text($value('activitytrobleshooting')),
                    'source_file' => $sourceFile,
                    'source_row' => $rowNumber,
                    'updated_by_user_id' => $request->user()->id,
                ];
                $record = LtroMttrRecord::query()->where('source_file', $sourceFile)->where('source_row', $rowNumber)->first();
                $record ? $record->update($values) : LtroMttrRecord::query()->create($values + ['created_by_user_id' => $request->user()->id]);
                $record ? $updated++ : $created++;
                $processed++;
            } catch (Throwable $exception) {
                $notes[] = "Baris {$rowNumber}: {$exception->getMessage()}";
            }
        }

        ActivityLog::write('ltro_import', 'ltro', "Import LTRO {$sourceFile}: {$processed} diproses, {$created} baru, {$updated} diperbarui.");
        $message = "Import selesai. {$processed} baris diproses ({$created} baru, {$updated} diperbarui).";

        return back()->with('success', $message)->with('import_warnings', array_slice($notes, 0, 10));
    }

    private function headers(array $rows): array
    {
        foreach (array_slice($rows, 0, 10, true) as $number => $row) {
            $headers = collect($row)->mapWithKeys(fn ($value, $column) => [preg_replace('/[^a-z0-9]+/', '', strtolower((string) $value)) => $column])->all();
            if (isset($headers['unit'], $headers['dateshutdown'])) {
                return [$number, $headers];
            }
        }

        return [0, ['no' => 'A', 'month' => 'B', 'rentalperiod' => 'C', 'dateshutdown' => 'D', 'daterunning' => 'E', 'unit' => 'F', 'timeshutdown' => 'G', 'timerunning' => 'H', 'downtimeminute' => 'I', 'category' => 'J', 'indication' => 'K', 'immediatecause' => 'L', 'activitytrobleshooting' => 'M', 'rh' => 'N', 'pk100' => 'O']];
    }

    private function text(mixed $value): ?string
    {
        $text = $value === null ? null : trim(preg_replace('/\s+/', ' ', (string) $value));

        return $text === '' ? null : rtrim($text, ',');
    }

    private function date(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        return $value instanceof DateTimeInterface ? Carbon::instance($value) : (is_numeric($value) ? Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value)) : Carbon::parse($value));
    }

    private function time(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : $this->date($value)?->format('H:i:s');
    }
}
