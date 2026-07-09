<?php

namespace App\Services;

use App\Models\AttendanceImport;
use App\Models\AttendanceRecord;
use App\Models\AttendanceResult;
use App\Models\WorkHourRecord;
use App\Models\WorkLocation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use Throwable;

class AttendanceReportProcessor
{
    public function process(AttendanceImport $import): void
    {
        try {
            /*
            |--------------------------------------------------------------------------
            | Hapus hasil proses lama
            |--------------------------------------------------------------------------
            */

            AttendanceRecord::query()
                ->where('attendance_import_id', $import->id)
                ->delete();

            AttendanceResult::query()
                ->where('attendance_import_id', $import->id)
                ->delete();

            WorkHourRecord::query()
                ->where('attendance_import_id', $import->id)
                ->delete();

            /*
            |--------------------------------------------------------------------------
            | Proses file
            |--------------------------------------------------------------------------
            */

            $this->processActivityFile($import);
            $this->processAllTimeFile($import);

            $import->update([
                'status' => 'processed',
                'processed_at' => now(),
                'notes' => 'Processed successfully at '
                    .now()->format('d M Y H:i:s'),
            ]);
        } catch (Throwable $exception) {
            /*
            |--------------------------------------------------------------------------
            | Bersihkan data setengah jadi
            |--------------------------------------------------------------------------
            */

            AttendanceRecord::query()
                ->where('attendance_import_id', $import->id)
                ->delete();

            AttendanceResult::query()
                ->where('attendance_import_id', $import->id)
                ->delete();

            WorkHourRecord::query()
                ->where('attendance_import_id', $import->id)
                ->delete();

            $import->update([
                'status' => 'failed',
                'processed_at' => null,
                'notes' => 'Processing failed: '
                    .$exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function processActivityFile(AttendanceImport $import): void
    {
        if (! $import->attendance_file_path) {
            throw new RuntimeException(
                'Attendance file path tidak tersedia.'
            );
        }

        $path = Storage::disk('public')
            ->path($import->attendance_file_path);

        if (! file_exists($path)) {
            throw new RuntimeException(
                'Attendance file tidak ditemukan: '.$path
            );
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, true, true);

        [$headerRowIndex, $headers] = $this->findHeaderRow($rows, [
            'date',
            'check_time',
            'type',
            'employee_id',
            'full_name',
        ]);

        if (! $headers) {
            throw new RuntimeException(
                'Header file Activity Attendance tidak sesuai.'
            );
        }

        $activityRows = [];

        foreach ($rows as $rowIndex => $row) {
            if ($rowIndex <= $headerRowIndex) {
                continue;
            }

            $data = $this->combineRow($headers, $row);

            $employeeCode = $this->cleanText($this->value($data, ['employee_id']));
            $employeeName = $this->cleanText($this->value($data, ['full_name']));
            $date = $this->parseDate($this->value($data, ['date']));
            $checkTime = $this->parseTime($this->value($data, ['check_time']));
            $type = $this->cleanText($this->value($data, ['type']));

            if (! $employeeCode || ! $employeeName || ! $date || ! $checkTime || ! $type) {
                continue;
            }

            $activityRows[] = [
                'date' => $date,
                'check_time' => $checkTime,
                'check_type' => $type,
                'employee_code' => $employeeCode,
                'employee_name' => $employeeName,
                'job_position' => $this->cleanText($this->value($data, ['job_position'])),
                'shift_name' => $this->cleanText($this->value($data, ['shift_name'])),
                'location_setting_name' => $this->cleanText($this->value($data, ['location_setting_name'])),
                'location_gps_name' => $this->cleanText($this->value($data, ['location_gps_name'])),
                'location_address' => $this->cleanText($this->value($data, ['location_address'])),
                'location_coordinate' => $this->cleanText($this->value($data, ['location_coordinate'])),
                'description' => $this->cleanText($this->value($data, ['description'])),
                'mobile_flag' => $this->cleanText($this->value($data, ['mobile_flag'])),
                'approval_status' => $this->cleanText($this->value($data, ['status'])),
            ];
        }

        $grouped = collect($activityRows)->groupBy(function (array $row): string {
            return $row['employee_code'].'|'.$row['date'];
        });

        $locations = WorkLocation::query()
            ->where('is_active', true)
            ->get();

        $attendanceInsertRows = [];
        $now = now();

        foreach ($grouped as $rowsInDay) {
            $clockInRow = $rowsInDay
                ->filter(fn (array $row): bool => strtolower($row['check_type']) === 'clock in')
                ->sortBy('check_time')
                ->first();

            $clockOutRow = $rowsInDay
                ->filter(fn (array $row): bool => strtolower($row['check_type']) === 'clock out')
                ->sortByDesc('check_time')
                ->first();

            $clockIn = $clockInRow['check_time'] ?? null;
            $clockOut = $clockOutRow['check_time'] ?? null;

            $durationMinutes = $this->durationMinutes($clockIn, $clockOut);
            $durationText = $this->durationText($clockIn, $clockOut);
            $checkoutCheck = $this->checkoutCheck($clockOut);

            $clockInMatch = $this->matchLocation(
                $clockInRow['location_coordinate'] ?? null,
                $locations,
                $clockInRow['location_gps_name'] ?? null
            );

            $clockOutMatch = $this->matchLocation(
                $clockOutRow['location_coordinate'] ?? null,
                $locations,
                $clockOutRow['location_gps_name'] ?? null
            );

            $locationCheck = $this->locationCheck($clockInMatch, $clockOutMatch);

            foreach ($rowsInDay as $row) {
                $rowMatch = $this->matchLocation(
                    $row['location_coordinate'],
                    $locations,
                    $row['location_gps_name']
                );

                $attendanceInsertRows[] = [
                    'attendance_import_id' => $import->id,

                    'employee_code' => $row['employee_code'],
                    'employee_name' => $row['employee_name'],
                    'job_position' => $row['job_position'],

                    'attendance_date' => $row['date'],
                    'check_time' => $row['check_time'],
                    'check_type' => $row['check_type'],

                    'clock_in' => $clockIn,
                    'clock_out' => $clockOut,

                    'shift_name' => $row['shift_name'],
                    'location_setting_name' => $row['location_setting_name'],
                    'location_gps_name' => $row['location_gps_name'],
                    'location_address' => $row['location_address'],
                    'location_coordinate' => $row['location_coordinate'],

                    'description' => $row['description'],
                    'mobile_flag' => $row['mobile_flag'],
                    'approval_status' => $row['approval_status'],

                    'work_minutes' => $durationMinutes,
                    'duration_minutes' => $durationMinutes,
                    'duration_text' => $durationText,

                    'distance_meters' => $rowMatch['distance_meters'] ?? null,

                    'matched_location_name' => $rowMatch['location_name'] ?? null,

                    'location_check' => $locationCheck,
                    'checkout_check' => $checkoutCheck,

                    'location_status' => $locationCheck,
                    'checkout_status' => $checkoutCheck,

                    'final_status' => $locationCheck === 'Sesuai'
                            ? 'ok'
                            : 'need_review',

                    'notes' => null,

                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                /*
                |--------------------------------------------------------------------------
                | Simpan per 500 baris
                |--------------------------------------------------------------------------
                */

                if (count($attendanceInsertRows) >= 500) {
                    AttendanceResult::query()
                        ->insert($attendanceInsertRows);

                    $attendanceInsertRows = [];
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Simpan sisa baris yang kurang dari 500
        |--------------------------------------------------------------------------
        */

        if ($attendanceInsertRows !== []) {
            AttendanceResult::query()
                ->insert($attendanceInsertRows);
        }
    }

    private function processAllTimeFile(AttendanceImport $import): void
    {
        if (! $import->work_hour_file_path) {
            throw new RuntimeException(
                'Work Hour file path tidak tersedia.'
            );
        }

        $path = Storage::disk('public')->path($import->work_hour_file_path);

        if (! file_exists($path)) {
            throw new RuntimeException(
                'Work Hour file tidak ditemukan: '.$path
            );
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, true, true);

        [$headerRowIndex, $headers] = $this->findHeaderRow($rows, [
            'employee_id',
            'full_name',
            'date',
            'real_working_hour',
        ]);

        if (! $headers) {
            throw new RuntimeException(
                'Header file Work Hour tidak sesuai.'
            );
        }

        $summaries = [];

        foreach ($rows as $rowIndex => $row) {
            if ($rowIndex <= $headerRowIndex) {
                continue;
            }

            $data = $this->combineRow($headers, $row);

            $employeeCode = $this->cleanText($this->value($data, ['employee_id']));
            $employeeName = $this->cleanText($this->value($data, ['full_name']));

            if (! $employeeCode || ! $employeeName) {
                continue;
            }

            $workHoursText = $this->cleanText($this->value($data, [
                'real_working_hour',
                'actual_working_hour',
            ]));

            $key = $employeeCode.'|'.$employeeName;

            if (! isset($summaries[$key])) {
                $summaries[$key] = [
                    'employee_code' => $employeeCode,
                    'employee_name' => $employeeName,
                    'total_minutes' => 0,
                ];
            }

            $summaries[$key]['total_minutes'] += $this->parseDurationToMinutes($workHoursText);
        }

        $workHourInsertRows = [];
        $now = now();

        foreach ($summaries as $summary) {
            $workHourInsertRows[] = [
                'attendance_import_id' => $import->id,

                'employee_code' => $summary['employee_code'],

                'employee_name' => $summary['employee_name'],

                'work_date' => null,

                'work_minutes' => $summary['total_minutes'],

                'work_hours_text' => $this->secondsToDurationText(
                    $summary['total_minutes'] * 60
                ),

                /*
                 * Batch insert tidak menjalankan cast model,
                 * sehingga JSON dibuat manual.
                 */
                'raw_data' => json_encode(
                    [
                        'period_name' => $import->period_name,
                        'source' => 'all time.xlsx',
                    ],
                    JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                        | JSON_THROW_ON_ERROR
                ),

                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($workHourInsertRows) >= 500) {
                WorkHourRecord::query()
                    ->insert($workHourInsertRows);

                $workHourInsertRows = [];
            }
        }

        if ($workHourInsertRows !== []) {
            WorkHourRecord::query()
                ->insert($workHourInsertRows);
        }
    }

    private function findHeaderRow(array $rows, array $requiredHeaders): array
    {
        foreach ($rows as $index => $row) {
            $headers = $this->normalizeHeaders($row);

            $headerValues = array_values($headers);

            $found = collect($requiredHeaders)
                ->every(fn (string $required): bool => in_array($required, $headerValues, true));

            if ($found) {
                return [$index, $headers];
            }
        }

        return [null, []];
    }

    private function normalizeHeaders(array $headers): array
    {
        $result = [];

        foreach ($headers as $key => $header) {
            $header = strtolower(trim((string) $header));
            $header = preg_replace('/[^a-z0-9]+/', '_', $header);
            $header = trim($header, '_');

            $result[$key] = $header;
        }

        return $result;
    }

    private function combineRow(array $headers, array $row): array
    {
        $data = [];

        foreach ($headers as $column => $header) {
            if (! $header) {
                continue;
            }

            $data[$header] = $row[$column] ?? null;
        }

        return $data;
    }

    private function value(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                return $data[$key];
            }
        }

        return null;
    }

    private function cleanText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function parseDate(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject($value))->format('Y-m-d');
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function parseTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject($value))->format('H:i:s');
        }

        try {
            return Carbon::parse((string) $value)->format('H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    private function parseCoordinate(?string $coordinate): array
    {
        if (! $coordinate) {
            return [null, null];
        }

        preg_match_all('/-?\d+(\.\d+)?/', $coordinate, $matches);

        if (count($matches[0]) < 2) {
            return [null, null];
        }

        return [
            (float) $matches[0][0],
            (float) $matches[0][1],
        ];
    }

    private function matchLocation(?string $coordinate, $locations, ?string $gpsName = null): ?array
    {
        [$latitude, $longitude] = $this->parseCoordinate($coordinate);

        if ($latitude === null || $longitude === null) {
            return null;
        }

        $candidateLocations = $this->filterLocationsByGpsName($locations, $gpsName);

        if ($candidateLocations->isEmpty()) {
            $candidateLocations = $locations;
        }

        $bestMatch = null;

        foreach ($candidateLocations as $location) {
            if ($location->latitude === null || $location->longitude === null) {
                continue;
            }

            $distance = $this->distanceMeters(
                $latitude,
                $longitude,
                (float) $location->latitude,
                (float) $location->longitude
            );

            if (! $bestMatch || $distance < $bestMatch['distance_meters']) {
                $bestMatch = [
                    'location_id' => $location->id,
                    'location_name' => $location->gps_name,
                    'distance_meters' => round($distance, 2),
                    'radius_meters' => (int) $location->radius_meters,
                    'is_inside_radius' => $distance <= (int) $location->radius_meters,
                    'is_flexible' => (bool) $location->is_flexible,
                ];
            }
        }

        return $bestMatch;
    }

    private function filterLocationsByGpsName($locations, ?string $gpsName)
    {
        $gpsName = strtolower((string) $gpsName);

        if ($gpsName === '') {
            return collect();
        }

        if (str_contains($gpsName, 'kpm') || str_contains($gpsName, 'oil') || str_contains($gpsName, 'gas')) {
            return $locations->filter(fn ($location) => str_contains(strtolower($location->gps_name), 'kpmog'));
        }

        if (str_contains($gpsName, 'apca')) {
            return $locations->filter(fn ($location) => str_contains(strtolower($location->gps_name), 'apca'));
        }

        if (str_contains($gpsName, 'alamanda') || str_contains($gpsName, 'project')) {
            return $locations->filter(fn ($location) => str_contains(strtolower($location->gps_name), 'alamanda'));
        }

        if (str_contains($gpsName, 'ltro')) {
            return $locations->filter(fn ($location) => str_contains(strtolower($location->gps_name), 'ltro'));
        }

        if (str_contains($gpsName, 'zul')) {
            return $locations->filter(fn ($location) => str_contains(strtolower($location->gps_name), 'zulfan'));
        }

        return collect();
    }

    private function locationCheck(?array $clockInMatch, ?array $clockOutMatch): string
    {
        if (! $clockInMatch || ! $clockOutMatch) {
            return 'Tidak Sesuai';
        }

        if (($clockInMatch['is_flexible'] ?? false) || ($clockOutMatch['is_flexible'] ?? false)) {
            return 'Sesuai';
        }

        if (! ($clockInMatch['is_inside_radius'] ?? false)) {
            return 'Tidak Sesuai';
        }

        if (! ($clockOutMatch['is_inside_radius'] ?? false)) {
            return 'Tidak Sesuai';
        }

        return $clockInMatch['location_id'] === $clockOutMatch['location_id']
            ? 'Sesuai'
            : 'Tidak Sesuai';
    }

    private function durationMinutes(?string $clockIn, ?string $clockOut): int
    {
        if (! $clockIn || ! $clockOut) {
            return 0;
        }

        $start = Carbon::parse($clockIn);
        $end = Carbon::parse($clockOut);

        if ($end->lessThan($start)) {
            $end->addDay();
        }

        return (int) $start->diffInMinutes($end);
    }

    private function durationText(?string $clockIn, ?string $clockOut): string
    {
        if (! $clockIn || ! $clockOut) {
            return '0:00:00';
        }

        $start = Carbon::parse($clockIn);
        $end = Carbon::parse($clockOut);

        if ($end->lessThan($start)) {
            $end->addDay();
        }

        $seconds = (int) $start->diffInSeconds($end);

        return $this->secondsToDurationText($seconds);
    }

    private function checkoutCheck(?string $clockOut): string
    {
        if (! $clockOut) {
            return 'Clock Out Tidak Ada';
        }

        $clockOutTime = Carbon::parse($clockOut);
        $limit = Carbon::parse('19:00:00');

        return $clockOutTime->greaterThanOrEqualTo($limit)
            ? 'Pulang 19:00 UP'
            : 'Pulang Sebelum 19:00';
    }

    private function secondsToDurationText(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%d:%02d:%02d', $hours, $minutes, $remainingSeconds);
    }

    private function parseDurationToMinutes(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            if ($value > 0 && $value < 1) {
                return (int) round($value * 24 * 60);
            }

            return (int) round($value * 60);
        }

        $value = trim((string) $value);

        if (preg_match('/^(\d+):(\d{1,2})(?::(\d{1,2}))?$/', $value, $matches)) {
            $hours = (int) $matches[1];
            $minutes = (int) $matches[2];

            return ($hours * 60) + $minutes;
        }

        return 0;
    }

    private function findPeriodColumn(array $headers, ?string $periodName): string
    {
        $ignored = [
            'employee_id',
            'full_name',
            'employee',
            'name',
            'no',
            'nik',
        ];

        $normalizedPeriodName = $this->normalizeHeaderText($periodName);

        if ($normalizedPeriodName) {
            foreach ($headers as $header) {
                if ($header && $this->normalizeHeaderText($header) === $normalizedPeriodName) {
                    return $header;
                }
            }
        }

        $candidate = null;

        foreach ($headers as $header) {
            if (! $header || in_array($header, $ignored, true)) {
                continue;
            }

            $candidate = $header;
        }

        return $candidate ?: 'total';
    }

    private function normalizeHeaderText(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);

        return trim($value, '_');
    }

    private function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;

        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2)
            + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)
        ));

        return $angle * $earthRadius;
    }
}
