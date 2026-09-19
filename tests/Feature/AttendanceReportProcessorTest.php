<?php

namespace Tests\Feature;

use App\Models\AttendanceImport;
use App\Models\AttendanceResult;
use App\Models\User;
use App\Models\WorkHourRecord;
use App\Models\WorkLocation;
use App\Services\AttendanceReportProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AttendanceReportProcessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_period_files_are_reconciled_without_missing_employee_names(): void
    {
        Storage::fake('local');
        Storage::disk('local')->makeDirectory('attendance-imports');

        $activityPath = 'attendance-imports/activity.xlsx';
        $workHourPath = 'attendance-imports/work-hours.xlsx';

        $this->writeActivityWorkbook(
            Storage::disk('local')->path($activityPath)
        );
        $this->writeWorkHourWorkbook(
            Storage::disk('local')->path($workHourPath)
        );

        $import = AttendanceImport::query()->create([
            'uploaded_by_user_id' => User::factory()->create()->id,
            'period_name' => '21 sep - 20 ags',
            'attendance_file_name' => 'activity.xlsx',
            'attendance_file_path' => $activityPath,
            'work_hour_file_name' => 'work-hours.xlsx',
            'work_hour_file_path' => $workHourPath,
            'status' => 'uploaded',
        ]);

        app(AttendanceReportProcessor::class)->process($import);

        $activityEmployeeCodes = AttendanceResult::query()
            ->where('attendance_import_id', $import->id)
            ->distinct()
            ->orderBy('employee_code')
            ->pluck('employee_code')
            ->all();

        $workHourEmployeeCodes = WorkHourRecord::query()
            ->where('attendance_import_id', $import->id)
            ->whereNull('work_date')
            ->orderBy('employee_code')
            ->pluck('employee_code')
            ->all();

        $this->assertSame(
            ['EMP001', 'EMP002', 'EMP003'],
            $activityEmployeeCodes
        );
        $this->assertSame($activityEmployeeCodes, $workHourEmployeeCodes);

        $this->assertDatabaseMissing('attendance_results', [
            'attendance_import_id' => $import->id,
            'employee_code' => 'EMP001',
            'employee_name' => 'Activity File Name',
        ]);
        $this->assertDatabaseHas('attendance_results', [
            'attendance_import_id' => $import->id,
            'employee_code' => 'EMP001',
            'employee_name' => 'Canonical Work Hour Name',
        ]);

        $this->assertDatabaseHas('attendance_results', [
            'attendance_import_id' => $import->id,
            'employee_code' => 'EMP002',
            'employee_name' => 'Only In Work Hour',
            'attendance_date' => null,
            'check_type' => 'Tidak Ada Activity',
            'location_check' => 'Tidak Ada Activity',
        ]);

        $this->assertDatabaseHas('work_hour_records', [
            'attendance_import_id' => $import->id,
            'employee_code' => 'EMP003',
            'employee_name' => 'Only In Activity',
            'work_minutes' => 0,
            'work_hours_text' => '0:00:00',
        ]);

        $this->assertDatabaseHas('work_hour_records', [
            'attendance_import_id' => $import->id,
            'employee_code' => 'EMP001',
            'employee_name' => 'Canonical Work Hour Name',
            'work_minutes' => 510,
            'work_hours_text' => '8:30:00',
        ]);
        $this->assertSame('processed', $import->fresh()->status);
        $this->assertSame(
            '21 Juli - 20 Agustus 2026',
            $import->fresh()->period_name
        );
        $this->assertSame(
            4,
            AttendanceResult::query()
                ->where('attendance_import_id', $import->id)
                ->where('location_check', 'Lokasi Belum Dikonfigurasi')
                ->count()
        );
    }

    public function test_multiple_coordinate_points_in_one_location_group_are_accepted(): void
    {
        Storage::fake('local');
        Storage::disk('local')->makeDirectory('attendance-imports');

        $activityPath = 'attendance-imports/activity-multiple-points.xlsx';
        $workHourPath = 'attendance-imports/work-hours-multiple-points.xlsx';

        $this->writeActivityWorkbook(
            Storage::disk('local')->path($activityPath),
            '-6.2855000,106.8075000'
        );
        $this->writeWorkHourWorkbook(
            Storage::disk('local')->path($workHourPath)
        );

        WorkLocation::query()->create([
            'gps_name' => 'KPMOG Office - Gate',
            'latitude' => -6.2849340,
            'longitude' => 106.8069252,
            'radius_meters' => 10,
            'is_active' => true,
        ]);
        WorkLocation::query()->create([
            'gps_name' => 'KPMOG Office - Building',
            'latitude' => -6.2855000,
            'longitude' => 106.8075000,
            'radius_meters' => 10,
            'is_active' => true,
        ]);

        $import = AttendanceImport::query()->create([
            'period_name' => 'periode salah',
            'attendance_file_path' => $activityPath,
            'work_hour_file_path' => $workHourPath,
            'status' => 'uploaded',
        ]);

        app(AttendanceReportProcessor::class)->process($import);

        $this->assertDatabaseHas('attendance_results', [
            'attendance_import_id' => $import->id,
            'employee_code' => 'EMP001',
            'check_type' => 'Clock In',
            'location_check' => 'Sesuai',
        ]);
        $this->assertDatabaseHas('attendance_results', [
            'attendance_import_id' => $import->id,
            'employee_code' => 'EMP001',
            'check_type' => 'Clock Out',
            'location_check' => 'Sesuai',
        ]);
    }

    public function test_work_hour_file_outside_21_to_20_period_is_rejected(): void
    {
        Storage::fake('local');
        Storage::disk('local')->makeDirectory('attendance-imports');

        $activityPath = 'attendance-imports/activity-invalid-period.xlsx';
        $workHourPath = 'attendance-imports/work-hours-invalid-period.xlsx';

        $this->writeActivityWorkbook(Storage::disk('local')->path($activityPath));
        $this->writeWorkHourWorkbook(
            Storage::disk('local')->path($workHourPath),
            '2026-08-19'
        );

        $import = AttendanceImport::query()->create([
            'period_name' => 'periode salah',
            'attendance_file_path' => $activityPath,
            'work_hour_file_path' => $workHourPath,
            'status' => 'uploaded',
        ]);

        try {
            app(AttendanceReportProcessor::class)->process($import);
            $this->fail('Periode selain tanggal 21–20 seharusnya ditolak.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString(
                'harus tanggal 21 sampai 20',
                $exception->getMessage()
            );
        }

        $this->assertSame('failed', $import->fresh()->status);
        $this->assertDatabaseMissing('attendance_results', [
            'attendance_import_id' => $import->id,
        ]);
    }

    private function writeActivityWorkbook(
        string $path,
        string $clockOutCoordinate = '-6.284934,106.8069252'
    ): void {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            [
                'Date',
                'Check Time',
                'Type',
                'Employee ID',
                'Full Name',
                'Job Position',
                'Face recognition',
                'Liveness validation',
                'Shift Name',
                'Shift Code',
                'Shift Label',
                'Location Setting Name',
                'Location GPS Name',
                'Location Address',
                'Location Coordinate',
                'Description',
                'Mobile Flag',
                'Status',
            ],
            [
                '2026-07-21', '08:00:00', 'Clock In', 'EMP001',
                'Activity File Name', null, null, null, 'Office', null,
                null, 'Branch', 'KPMOG', 'Office', '-6.284934,106.8069252',
                null, 'Mobile App', 'Approved',
            ],
            [
                '2026-07-21', '17:00:00', 'Clock Out', 'EMP001',
                'Activity File Name', null, null, null, 'Office', null,
                null, 'Branch', 'KPMOG', 'Office', $clockOutCoordinate,
                null, 'Mobile App', 'Approved',
            ],
            [
                '2026-07-21', '08:30:00', 'Clock In', 'EMP003',
                'Only In Activity', null, null, null, 'Office', null,
                null, 'Branch', 'KPMOG', 'Office', '-6.284934,106.8069252',
                null, 'Mobile App', 'Approved',
            ],
            [
                '2026-07-21', '17:30:00', 'Clock Out', 'EMP003',
                'Only In Activity', null, null, null, 'Office', null,
                null, 'Branch', 'KPMOG', 'Office', '-6.284934,106.8069252',
                null, 'Mobile App', 'Approved',
            ],
        ]);

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
    }

    private function writeWorkHourWorkbook(
        string $path,
        string $periodEnd = '2026-08-20'
    ): void {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Employee ID', 'Full Name', 'Date', 'Real Working Hour'],
            ['EMP001', 'Canonical Work Hour Name', '2026-07-21', '04:00'],
            ['EMP001', 'Canonical Work Hour Name', '2026-07-22', '04:30'],
            ['EMP002', 'Only In Work Hour', '2026-07-21', '08:00'],
            ['EMP002', 'Only In Work Hour', $periodEnd, '00:00'],
        ]);

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
    }
}
