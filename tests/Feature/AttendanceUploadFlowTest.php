<?php

namespace Tests\Feature;

use App\Filament\Pages\AttendanceReportCenter;
use App\Filament\Resources\AttendanceImports\AttendanceImportResource;
use App\Filament\Resources\AttendanceImports\Pages\CreateAttendanceImport;
use App\Models\AttendanceImport;
use App\Models\Permission;
use App\Models\User;
use App\Services\AttendanceReportProcessor;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Tests\TestCase;

class AttendanceUploadFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Attendance flow tests require SQLite :memory:.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function user(array $permissions): User
    {
        $user = User::factory()->create(['is_admin' => false]);
        foreach ($permissions as $code) {
            $user->directPermissions()->attach(Permission::firstOrCreate(['code' => $code], ['name' => $code, 'is_active' => true]));
        }
        $this->actingAs($user);

        return $user;
    }

    private function file(string $name, array $rows): UploadedFile
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray($rows);
        $path = Storage::disk('local')->path($name);
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return UploadedFile::fake()->createWithContent($name, file_get_contents($path));
    }

    private function files(bool $validPeriod = true): array
    {
        return [
            'attendance_file_path' => $this->file('activity.xlsx', [
                ['Date', 'Check Time', 'Type', 'Employee ID', 'Full Name'],
                ['2026-08-21', '08:00:00', 'Clock In', '00123', 'Attendance sample'],
                ['2026-08-21', '17:00:00', 'Clock Out', '00123', 'Attendance sample'],
            ]),
            'work_hour_file_path' => $this->file('hours.xlsx', [
                ['Employee ID', 'Full Name', 'Date', 'Real Working Hour'],
                ['00123', 'Attendance sample', '2026-08-21', '08:00'],
                ['00123', 'Attendance sample', $validPeriod ? '2026-09-20' : '2026-09-19', '08:00'],
            ]),
        ];
    }

    public function test_manager_uploads_two_files_and_goes_directly_to_processed_results(): void
    {
        $user = $this->user(['attendance.manage']);
        $page = Livewire::test(CreateAttendanceImport::class)->fillForm($this->files())->call('create')->assertHasNoFormErrors();
        $import = AttendanceImport::sole();
        $this->assertSame('processed', $import->status);
        $this->assertSame('21 Agustus - 20 September 2026', $import->period_name);
        $this->assertSame($user->id, $import->uploaded_by_user_id);
        $this->assertSame(960, $import->workHourRecords()->sole()->work_minutes);
        $this->assertSame(2, $import->results()->count());
        $page->assertRedirect(AttendanceImportResource::getUrl('results', ['record' => $import]));
    }

    public function test_invalid_period_keeps_failed_upload_and_returns_to_its_status(): void
    {
        $this->user(['attendance.manage']);
        $page = Livewire::test(CreateAttendanceImport::class)->fillForm($this->files(false))->call('create')->assertHasNoFormErrors();
        $import = AttendanceImport::sole();
        $this->assertSame('failed', $import->status);
        $this->assertSame(0, $import->results()->count());
        $page->assertRedirect(AttendanceReportCenter::getUrl(['periode' => $import->id]));
        Livewire::withQueryParams(['periode' => $import->id])->test(AttendanceReportCenter::class)
            ->assertSee('Gagal diproses')->assertSee('Perbaiki file')->assertDontSee('Lihat hasil laporan');
    }

    public function test_uploader_can_save_but_cannot_process_or_download(): void
    {
        $this->user(['attendance.upload']);
        $this->mock(AttendanceReportProcessor::class)->shouldNotReceive('process');
        Livewire::test(CreateAttendanceImport::class)->assertSee('Upload data')->fillForm($this->files())->call('create')->assertHasNoFormErrors();
        $import = AttendanceImport::sole();
        $this->assertSame('uploaded', $import->status);
        $page = Livewire::test(AttendanceReportCenter::class)->assertSee('Menunggu proses')->assertActionHidden('process');
        $page->call('mountAction', 'process')->assertActionNotMounted('process');
        $this->assertSame('uploaded', $import->fresh()->status);
        $this->get(route('attendance-imports.download', $import))->assertForbidden();
    }

    public function test_reader_sees_results_but_no_upload_or_process_controls(): void
    {
        $user = $this->user(['attendance.view']);
        AttendanceImport::create(['uploaded_by_user_id' => $user->id, 'period_name' => 'August', 'status' => 'processed']);
        Livewire::test(AttendanceReportCenter::class)->assertSee('Lihat hasil laporan')
            ->assertSee('Unduh Excel')->assertActionHidden('upload')->assertActionHidden('manageUploads');
        $this->get('/panel/attendance-imports/create')->assertForbidden();
        $this->assertFalse(AttendanceImportResource::shouldRegisterNavigation());
    }

    public function test_empty_history_has_no_dead_result_links_and_files_are_required(): void
    {
        $this->user(['attendance.manage']);
        Livewire::test(AttendanceReportCenter::class)->assertSee('Belum ada laporan attendance.')
            ->assertDontSee('Lihat hasil laporan')->assertActionVisible('upload');
        Livewire::test(CreateAttendanceImport::class)->call('create')
            ->assertHasFormErrors(['attendance_file_path' => 'required', 'work_hour_file_path' => 'required']);
        $this->assertDatabaseCount('attendance_imports', 0);
    }

    public function test_manager_can_process_a_saved_upload_from_history(): void
    {
        $user = $this->user(['attendance.upload']);
        Livewire::test(CreateAttendanceImport::class)->fillForm($this->files())->call('create');
        $import = AttendanceImport::sole();
        $user->directPermissions()->attach(Permission::firstOrCreate(['code' => 'attendance.manage'], ['name' => 'Manage', 'is_active' => true]));
        $this->actingAs($user->fresh());
        Livewire::withQueryParams(['periode' => $import->id])->test(AttendanceReportCenter::class)
            ->assertActionVisible('process')->callAction('process')
            ->assertRedirect(AttendanceImportResource::getUrl('results', ['record' => $import]));
        $this->assertSame('processed', $import->fresh()->status);
    }

    public function test_failed_process_from_history_displays_the_updated_failure(): void
    {
        $user = $this->user(['attendance.upload']);
        Livewire::test(CreateAttendanceImport::class)->fillForm($this->files(false))->call('create');
        $import = AttendanceImport::sole();
        $user->directPermissions()->attach(Permission::firstOrCreate(['code' => 'attendance.manage'], ['name' => 'Manage', 'is_active' => true]));
        $this->actingAs($user->fresh());
        Livewire::withQueryParams(['periode' => $import->id])->test(AttendanceReportCenter::class)
            ->callAction('process')->assertSee('Gagal diproses')->assertSee('Proses belum berhasil.')
            ->assertSee('Perbaiki file')->assertDontSee('Lihat hasil laporan');
        $this->assertSame('failed', $import->fresh()->status);
    }
}
