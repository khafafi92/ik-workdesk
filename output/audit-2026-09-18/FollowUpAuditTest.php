<?php

namespace Tests\Audit;

use App\Filament\Resources\ActivityReports\Pages\ListActivityReports;
use App\Filament\Resources\AttendanceImports\Pages\CreateAttendanceImport;
use App\Filament\Resources\PermitCompanies\Pages\EditPermitCompany;
use App\Filament\Resources\PermitCompanies\RelationManagers\KblisRelationManager;
use App\Models\ActivityCategory;
use App\Models\AttendanceImport;
use App\Models\DailyActivity;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\PermitCompany;
use App\Models\PermitKbli;
use App\Models\User;
use App\Services\AttendanceReportProcessor;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class FollowUpAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Audit aborted before migrations: SQLite :memory: is required.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Notification::fake();
        Mail::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function admin(): User
    {
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);

        return $user;
    }

    private function activity(User $user, string $title, string $date, int $minutes): DailyActivity
    {
        $category = ActivityCategory::firstOrCreate(['code' => 'FOLLOWUP'], ['name' => 'Followup', 'is_active' => true]);

        return DailyActivity::create(['user_id' => $user->id, 'title' => $title, 'work_date' => $date, 'start_time' => '09:00', 'end_time' => '10:00', 'duration_minutes' => $minutes, 'source_type' => 'manual', 'work_context' => 'operational', 'activity_category_id' => $category->id, 'requester_type' => 'company', 'requester_company_name' => 'Audit Company']);
    }

    public function test_activity_report_period_and_user_filters_recalculate_total(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create();
        $inside = $this->activity($admin, 'Inside filter', '2026-09-20', 60);
        $outside = $this->activity($admin, 'Outside period', '2026-09-18', 120);
        $otherRow = $this->activity($other, 'Other person', '2026-09-20', 90);
        $page = Livewire::test(ListActivityReports::class)
            ->assertCanSeeTableRecords([$inside, $outside, $otherRow])
            ->filterTable('period', ['from' => '2026-09-20', 'until' => '2026-09-20'])
            ->filterTable('user_id', $admin->id)
            ->assertCanSeeTableRecords([$inside])
            ->assertCanNotSeeTableRecords([$outside, $otherRow]);
        $summarizer = array_values($page->instance()->getTable()->getColumn('duration_minutes')->getSummarizers())[0];
        $total = $summarizer->query($page->instance()->getAllTableSummaryQuery())->selectedState([])->getState();
        $this->assertSame(60, (int) $total);
    }

    public function test_manager_filter_cannot_reveal_another_departments_activities(): void
    {
        $deptA = Department::create(['code' => 'FOLLOW-A', 'name' => 'Department A', 'is_active' => true]);
        $deptB = Department::create(['code' => 'FOLLOW-B', 'name' => 'Department B', 'is_active' => true]);
        $manager = User::factory()->create(['is_admin' => false]);
        $team = User::factory()->create(['is_admin' => false]);
        $outsider = User::factory()->create(['is_admin' => false]);
        foreach ([[$manager, $deptA], [$team, $deptA], [$outsider, $deptB]] as [$user,$dept]) {
            Employee::create(['user_id' => $user->id, 'department_id' => $dept->id, 'name' => 'Audit Employee', 'is_active' => true]);
        }
        $permission = Permission::create(['code' => 'worklogs.manage', 'name' => 'Manage work logs', 'is_active' => true]);
        $manager->directPermissions()->attach($permission);
        $own = $this->activity($manager, 'Manager activity', '2026-09-20', 60);
        $teamRow = $this->activity($team, 'Team activity', '2026-09-20', 60);
        $otherRow = $this->activity($outsider, 'Private other department', '2026-09-20', 60);
        $this->actingAs($manager);
        Livewire::test(ListActivityReports::class)
            ->assertCanSeeTableRecords([$own, $teamRow])
            ->assertCanNotSeeTableRecords([$otherRow])
            ->filterTable('user_id', $outsider->id)
            ->assertCanNotSeeTableRecords([$own, $teamRow, $otherRow]);
    }

    public function test_kbli_relation_create_edit_delete(): void
    {
        $this->admin();
        $company = PermitCompany::create(['code' => 'FOLLOW', 'name' => 'Audit Company', 'is_active' => true]);
        $page = Livewire::test(KblisRelationManager::class, ['ownerRecord' => $company, 'pageClass' => EditPermitCompany::class]);
        $page->callTableAction('create', data: ['code' => '12345', 'name' => 'Audit KBLI', 'is_active' => true])->assertHasNoFormErrors();
        $record = PermitKbli::where('permit_company_id', $company->id)->sole();
        $page->callTableAction('edit', $record, data: ['code' => '12345', 'name' => 'Audit KBLI Edited', 'is_active' => true])->assertHasNoFormErrors();
        $this->assertSame('Audit KBLI Edited', $record->fresh()->name);
        $page->callTableAction('delete', $record);
        $this->assertNull($record->fresh());
    }

    public function test_kbli_exact_duplicate_is_rejected(): void
    {
        $this->admin();
        $company = PermitCompany::create(['code' => 'FOLLOW', 'name' => 'Audit Company', 'is_active' => true]);
        PermitKbli::create(['permit_company_id' => $company->id, 'code' => '12345', 'name' => 'Audit KBLI', 'is_active' => true]);
        Livewire::test(KblisRelationManager::class, ['ownerRecord' => $company, 'pageClass' => EditPermitCompany::class])
            ->callTableAction('create', data: ['code' => '12345', 'name' => 'Audit KBLI', 'is_active' => true])->assertHasFormErrors(['name']);
        $this->assertSame(1, $company->kblis()->count());
    }

    public function test_attendance_upload_requires_both_files(): void
    {
        $this->admin();
        Storage::fake('local');
        Livewire::test(CreateAttendanceImport::class)->fillForm(['period_name' => 'Audit'])
            ->call('create')->assertHasFormErrors(['attendance_file_path', 'work_hour_file_path']);
        $this->assertDatabaseCount('attendance_imports', 0);
    }

    public function test_two_real_xlsx_uploads_create_and_process(): void
    {
        $user = $this->admin();
        Storage::fake('local');
        $activity = $this->workbook('audit-activity.xlsx', [
            ['Date', 'Check Time', 'Type', 'Employee ID', 'Full Name'],
            ['2026-07-21', '08:00:00', 'Clock In', 'AUDIT001', 'Audit Employee'],
            ['2026-07-21', '17:00:00', 'Clock Out', 'AUDIT001', 'Audit Employee'],
        ]);
        $hours = $this->workbook('audit-hours.xlsx', [
            ['Employee ID', 'Full Name', 'Date', 'Real Working Hour'],
            ['AUDIT001', 'Audit Employee', '2026-07-21', '08:00'],
            ['AUDIT001', 'Audit Employee', '2026-08-20', '00:00'],
        ]);
        Livewire::test(CreateAttendanceImport::class)
            ->fillForm(['period_name' => 'Audit period', 'attendance_file_path' => $activity, 'work_hour_file_path' => $hours])
            ->call('create')->assertHasNoFormErrors();
        $import = AttendanceImport::sole();
        $this->assertSame($user->id, $import->uploaded_by_user_id);
        Storage::disk('local')->assertExists($import->attendance_file_path);
        Storage::disk('local')->assertExists($import->work_hour_file_path);
        app(AttendanceReportProcessor::class)->process($import);
        $this->assertSame('processed', $import->fresh()->status);
        $this->assertSame('21 Juli - 20 Agustus 2026', $import->fresh()->period_name);
        $this->assertDatabaseHas('attendance_results', ['attendance_import_id' => $import->id, 'employee_code' => 'AUDIT001']);
        $this->assertDatabaseHas('work_hour_records', ['attendance_import_id' => $import->id, 'employee_code' => 'AUDIT001', 'work_minutes' => 480]);
    }

    private function workbook(string $name, array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows);
        $path = Storage::disk('local')->path($name);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return UploadedFile::fake()->createWithContent($name,file_get_contents($path));
    }
}
