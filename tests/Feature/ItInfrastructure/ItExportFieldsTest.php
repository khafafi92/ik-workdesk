<?php

namespace Tests\Feature\ItInfrastructure;

use App\Enums\ItDeviceType;
use App\Enums\ItInspectionCondition;
use App\Exports\ItInspectionExport;
use App\Exports\ItMaintenanceSummaryExport;
use App\Filament\Pages\ItInfrastructureReportPage;
use App\Filament\Resources\ItDevices\Pages\CreateItDevice;
use App\Models\Department;
use App\Models\ItDevice;
use App\Models\ItInspectionResult;
use App\Models\WorkLocation;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\Support\ItInfrastructureTestCase;

class ItExportFieldsTest extends ItInfrastructureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->inspector->update(['is_admin' => true, 'name' => 'User terdaftar']);
        $this->actingAs($this->inspector);
    }

    private function workbook(object $export): Spreadsheet
    {
        $file = tempnam(sys_get_temp_dir(), 'it-master-export-');
        try {
            file_put_contents($file, Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX));
            Cell::setValueBinder(new DefaultValueBinder);

            return IOFactory::load($file);
        } finally {
            unlink($file);
        }
    }

    public function test_both_workbooks_match_master_fields_and_keep_historical_values(): void
    {
        $this->inspector->employee->update(['position' => 'IT Officer']);
        Livewire::test(CreateItDevice::class)->fillForm([
            'asset_number' => '00035/APCA/IT/2025', 'name' => '=Laptop terdaftar', 'device_type' => 'laptop',
            'location_name' => 'Office HO', 'location_detail' => 'Ruang IT', 'serial_number' => '001234', 'specifications' => 'Core i5',
            'operating_system' => 'Ubuntu', 'windows_version' => '24.04 LTS', 'windows_license_status' => 'not_applicable',
            'office_version' => '2024', 'office_license_status' => 'valid', 'ram_gb' => 16,
            'storage_devices' => [['type' => 'ssd', 'capacity_gb' => 512], ['type' => 'hdd', 'capacity_gb' => 1024]],
            'assigned_employee_id' => $this->inspector->employee->id, 'lifecycle_status' => 'in_use', 'notes' => '=Catatan asli',
        ])->call('create')->assertHasNoFormErrors();
        $device = ItDevice::query()->sole();
        $inspection = $this->inspection($device);
        $this->followUp($this->inspectionResult($inspection, ItInspectionCondition::Warning));
        $this->fillRemainingResults($inspection);
        $this->finalize($inspection);
        $before = $inspection->fresh()->getRawOriginal();

        $device->update(['name' => 'Laptop diperbarui', 'location_name' => 'Office Alamanda', 'location_detail' => 'Meja 8', 'serial_number' => '005678', 'is_active' => false]);
        $device->computerDetail()->firstOrFail()->update(['windows_version' => '26.04 LTS', 'ram_gb' => 32]);
        $division = Department::query()->create(['code' => 'OPS', 'name' => 'Operations']);
        $this->inspector->employee->update(['position' => 'Supervisor', 'department_id' => $division->id]);
        $this->inspector->update(['name' => 'Nama terbaru']);

        $page = Livewire::test(ItInfrastructureReportPage::class)->instance();
        $summary = $this->workbook(new ItMaintenanceSummaryExport($page->latestDevicesQuery(), $page->inspectionQuery()->where('status', 'finalized'), $page->findingsQuery()));
        $details = $this->workbook(new ItInspectionExport(ItInspectionResult::query()->where('it_inspection_id', $inspection->id)));
        try {
            $headings = ['Nomor Aset', 'Nama Perangkat', 'Jenis perangkat / objek', 'Department pengelola', 'Lokasi', 'LT (Lantai)', 'Detail ruang / rack', 'Serial Number', 'Spesifikasi', 'OS', 'Versi', 'Lisensi', 'Versi Microsoft Office', 'Lisensi Microsoft Office', 'RAM (GB)', 'HDD / SSD', 'Nama User', 'Jabatan', 'Divisi', 'Status Pemakaian', 'Aktif untuk pemeriksaan baru', 'Tanggal penghapusan', 'Alasan penghapusan', 'Keterangan'];
            $historical = ['00035/APCA/IT/2025', '=Laptop terdaftar', 'Laptop', 'IT', 'Office HO', null, 'Ruang IT', '001234', 'Core i5', 'Ubuntu', '24.04 LTS', 'Tidak Berlaku', '2024', 'Original/Valid', 16.0, 'SSD 512 GB; HDD 1024 GB', 'User terdaftar', 'IT Officer', 'IT', 'Digunakan', 'Ya', null, null, '=Catatan asli'];
            foreach ([$summary->getSheet(0), $summary->getSheet(1), $details->getActiveSheet()] as $sheet) {
                $rows = $sheet->toArray(null, true, false);
                $this->assertSame($headings, array_slice($rows[0], 0, 24));
                foreach (['Kategori', 'Bentuk objek', 'Status register', 'Kode register', 'Nama perangkat / ruang', 'Dasar verifikasi lisensi OS', 'Dasar verifikasi lisensi Office'] as $removed) {
                    $this->assertNotContains($removed, $rows[0]);
                }
                if ($sheet === $summary->getSheet(0)) {
                    $expected = $historical;
                    foreach ([1 => 'Laptop diperbarui', 4 => 'Office Alamanda', 6 => 'Meja 8', 7 => '005678', 10 => '26.04 LTS', 14 => 32.0, 16 => 'Nama terbaru', 17 => 'Supervisor', 18 => 'Operations', 20 => 'Tidak'] as $index => $value) {
                        $expected[$index] = $value;
                    }
                } else {
                    $expected = $historical;
                }
                $this->assertSame($expected, array_slice($rows[1], 0, 24));
                $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A2')->getDataType());
                $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('H2')->getDataType());
                $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('O2')->getDataType());
                $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('X2')->getDataType());
            }
            $this->assertSame($before, $inspection->fresh()->getRawOriginal());
        } finally {
            $summary->disconnectWorksheets();
            $details->disconnectWorksheets();
        }
    }

    public function test_old_snapshots_keep_unknown_fields_blank_instead_of_reading_current_master(): void
    {
        $device = $this->device();
        $inspection = $this->inspection($device);
        $this->inspectionResult($inspection, attributes: ['measured_value' => ['value' => 0]]);
        $snapshot = $inspection->device_snapshot;
        unset($snapshot['computer_detail'], $snapshot['assigned_employee'], $snapshot['assigned_user_name'], $snapshot['is_active']);
        DB::table('it_inspections')->where('id', $inspection->id)->update(['device_snapshot' => json_encode($snapshot)]);
        $device->computerDetail()->create(['operating_system' => 'Windows', 'windows_version' => '11']);
        $device->update(['assigned_user_name' => 'Pemakai baru']);
        $export = new ItInspectionExport(ItInspectionResult::query()->where('it_inspection_id', $inspection->id));
        $row = array_combine($export->headings(), $export->map($export->query()->sole()));
        foreach (['OS', 'Versi', 'Nama User', 'Jabatan', 'Divisi', 'Aktif untuk pemeriksaan baru'] as $field) {
            $this->assertNull($row[$field]);
        }
        $workbook = $this->workbook($export);
        try {
            $cell = $workbook->getActiveSheet()->getCell([array_search('Nilai / rincian', $export->headings(), true) + 1, 2]);
            $this->assertSame(0, $cell->getValue());
            $this->assertSame(DataType::TYPE_NUMERIC, $cell->getDataType());
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    public function test_room_and_switch_exports_keep_location_floor_and_rack_separate_without_inventing_computer_fields(): void
    {
        $room = $this->device(ItDeviceType::ServerRoom, ['location_detail' => 'Ruang server']);
        $switch = $this->device(ItDeviceType::Switch, ['floor' => 'B2', 'location_detail' => 'Rack 2']);
        $page = Livewire::test(ItInfrastructureReportPage::class)->instance();
        [$sheet] = (new ItMaintenanceSummaryExport($page->latestDevicesQuery(), $page->inspectionQuery()->where('status', 'finalized'), $page->findingsQuery()))->sheets();
        foreach ($sheet->query()->get() as $device) {
            $row = array_combine($sheet->headings(), $sheet->map($device));
            $this->assertSame('Office HO', $row['Lokasi']);
            $this->assertSame($device->id === $switch->id ? 'B2' : null, $row['LT (Lantai)']);
            $this->assertSame($device->id === $room->id ? 'Ruang server' : 'Rack 2', $row['Detail ruang / rack']);
            foreach (['OS', 'Versi', 'Lisensi', 'RAM (GB)'] as $field) {
                $this->assertNull($row[$field]);
            }
            if ($device->id === $room->id) {
                $this->assertNull($row['Nomor Aset']);
                $this->assertNull($row['Status Pemakaian']);
            }
        }
    }

    public function test_current_master_location_does_not_fall_back_to_old_attendance_master(): void
    {
        $location = WorkLocation::query()->create(['gps_name' => 'KPMOG OFFICE']);
        $device = $this->device(attributes: ['location_name' => null, 'work_location_id' => $location->id]);
        $page = Livewire::test(ItInfrastructureReportPage::class)->instance();
        [$sheet] = (new ItMaintenanceSummaryExport($page->latestDevicesQuery(), $page->inspectionQuery()->where('status', 'finalized'), $page->findingsQuery()))->sheets();
        $row = array_combine($sheet->headings(), $sheet->map($sheet->query()->sole()));
        $this->assertNull($row['Lokasi']);
        $this->assertSame($location->id, $device->fresh()->work_location_id);
    }
}
