<?php

namespace Tests\Feature;

use App\Filament\Resources\AttendanceImports\AttendanceImportResource;
use App\Filament\Resources\AttendanceImports\Pages\ViewAttendanceImportResults;
use App\Filament\Resources\AttendanceResults\Pages\CreateAttendanceResult;
use App\Filament\Resources\AttendanceResults\Pages\EditAttendanceResult;
use App\Filament\Resources\AttendanceResults\Pages\ListAttendanceResults;
use App\Filament\Resources\Departments\Pages\CreateDepartment;
use App\Filament\Resources\Departments\Pages\EditDepartment;
use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Models\AttendanceImport;
use App\Models\AttendanceResult;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Regression tests require SQLite :memory: before running migrations.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function admin(): User
    {
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);

        return $user;
    }

    public function test_attendance_reader_can_filter_results_without_upload_or_edit_access(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $permission = Permission::create(['code' => 'attendance.view', 'name' => 'View attendance', 'is_active' => true]);
        $user->directPermissions()->attach($permission);
        $import = AttendanceImport::create(['uploaded_by_user_id' => $user->id, 'period_name' => 'September', 'status' => 'processed']);
        AttendanceResult::create(['attendance_import_id' => $import->id, 'employee_code' => '00123', 'employee_name' => 'Reader sample', 'work_minutes' => 480]);
        $this->actingAs($user);

        $this->get('/panel/attendance-report-center')->assertOk()->assertDontSee('New Period');
        $this->get('/panel/attendance-imports/'.$import->id.'/results')->assertOk();
        $page = Livewire::test(ViewAttendanceImportResults::class, ['record' => $import->id])
            ->set('search', '00123')->call('applyFilters')->assertSee('Reader sample');
        $this->assertFalse(AttendanceImportResource::canCreate());
        $this->assertFalse(AttendanceImportResource::canEdit($import));
        $this->assertFalse(AttendanceImportResource::canDelete($import));
        $this->get('/panel/attendance-imports/create')->assertForbidden();
        $this->get('/panel/attendance-imports/'.$import->id.'/edit')->assertForbidden();

        $user->directPermissions()->detach();
        $this->actingAs($user->fresh());
        $page->call('clearFilters')->assertForbidden();
        $this->get('/panel/attendance-imports/'.$import->id.'/results')->assertForbidden();
    }

    public function test_existing_uploader_can_still_open_results(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $permission = Permission::create(['code' => 'attendance.upload', 'name' => 'Upload attendance', 'is_active' => true]);
        $user->directPermissions()->attach($permission);
        $import = AttendanceImport::create(['uploaded_by_user_id' => $user->id, 'period_name' => 'September', 'status' => 'processed']);
        $this->actingAs($user)->get('/panel/attendance-imports/'.$import->id.'/results')->assertOk();
    }

    public function test_department_code_uniqueness_on_create_and_edit(): void
    {
        $this->admin();
        $first = Department::create(['code' => 'OPS', 'name' => 'Operations', 'is_active' => true]);
        $second = Department::create(['code' => 'HR', 'name' => 'HR', 'is_active' => true]);
        Livewire::test(CreateDepartment::class)->fillForm(['code' => 'OPS', 'name' => 'Duplicate', 'is_active' => true])
            ->call('create')->assertHasFormErrors(['code' => 'unique']);
        Livewire::test(EditDepartment::class, ['record' => $second->id])->fillForm(['code' => 'OPS'])
            ->call('save')->assertHasFormErrors(['code' => 'unique']);
        Livewire::test(EditDepartment::class, ['record' => $first->id])->fillForm(['name' => 'Renamed operations'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('Renamed operations', $first->fresh()->name);
        $this->assertSame('HR', $second->fresh()->code);
        $this->assertDatabaseCount('departments', 2);
    }

    public function test_employee_account_options_and_uniqueness_preserve_current_account(): void
    {
        $this->admin();
        $taken = User::factory()->create();
        $available = User::factory()->create();
        $employee = Employee::create(['user_id' => $taken->id, 'name' => 'Existing', 'is_active' => true]);
        Livewire::test(CreateEmployee::class)
            ->assertFormFieldExists('user_id', fn (Select $field): bool => ! array_key_exists($taken->id, $field->getOptions()) && array_key_exists($available->id, $field->getOptions()))
            ->fillForm(['user_id' => $taken->id, 'name' => 'Duplicate', 'is_active' => true])
            ->call('create')->assertHasFormErrors(['user_id']);
        Livewire::test(EditEmployee::class, ['record' => $employee->id])
            ->assertFormFieldExists('user_id', fn (Select $field): bool => array_key_exists($taken->id, $field->getOptions()))
            ->fillForm(['name' => 'Renamed employee'])->call('save')->assertHasNoFormErrors();
        Livewire::test(CreateEmployee::class)->fillForm(['user_id' => $available->id, 'name' => 'New employee', 'is_active' => true])
            ->call('create')->assertHasNoFormErrors();
        $second = Employee::where('user_id', $available->id)->sole();
        Livewire::test(EditEmployee::class, ['record' => $second->id])->fillForm(['user_id' => $taken->id])
            ->call('save')->assertHasFormErrors(['user_id']);
        $this->assertSame($available->id, $second->fresh()->user_id);
        $this->assertSame('Renamed employee', $employee->fresh()->name);
        $this->assertDatabaseCount('employees', 2);
    }

    public static function invalidAttendanceValues(): array
    {
        return [
            'negative minutes' => ['work_minutes', -60],
            'fractional minutes' => ['work_minutes', 1.5],
            'overflow minutes' => ['work_minutes', 2147483648],
            'negative distance' => ['distance_meters', -1],
            'missing period' => ['attendance_import_id', 999999],
        ];
    }

    #[DataProvider('invalidAttendanceValues')]
    public function test_attendance_rejects_invalid_values_on_create_and_edit(string $field, int|float $value): void
    {
        $this->admin();
        Livewire::test(CreateAttendanceResult::class)->fillForm(array_replace(['employee_name' => 'Sample', 'work_minutes' => 60], [$field => $value]))
            ->call('create')->assertHasFormErrors([$field]);
        $this->assertDatabaseCount('attendance_results', 0);
        $row = AttendanceResult::create(['employee_name' => 'Existing', 'work_minutes' => 60]);
        Livewire::test(EditAttendanceResult::class, ['record' => $row->id])->fillForm([$field => $value])
            ->call('save')->assertHasFormErrors([$field]);
        $this->assertEquals(60, $row->fresh()->work_minutes);
    }

    public function test_attendance_accepts_valid_period_and_zero_values(): void
    {
        $admin = $this->admin();
        $import = AttendanceImport::create(['uploaded_by_user_id' => $admin->id, 'period_name' => 'September', 'status' => 'processed']);
        Livewire::test(CreateAttendanceResult::class)->fillForm(['employee_name' => 'Sample', 'attendance_import_id' => $import->id, 'work_minutes' => 0, 'distance_meters' => 0])
            ->call('create')->assertHasNoFormErrors();
        $row = AttendanceResult::sole();
        Livewire::test(EditAttendanceResult::class, ['record' => $row->id])->fillForm(['work_minutes' => 480])
            ->call('save')->assertHasNoFormErrors();
        $this->assertEquals(480, $row->fresh()->work_minutes);
        $this->assertEquals($import->id, $row->attendance_import_id);
    }

    public function test_processed_employee_code_and_duration_are_displayed_and_searchable(): void
    {
        $this->admin();
        $row = AttendanceResult::create(['employee_code' => '00123', 'employee_name' => 'Sample', 'duration_text' => '8:00:00', 'work_minutes' => 480]);
        $other = AttendanceResult::create(['employee_code' => '00999', 'employee_name' => 'Other', 'duration_text' => '4:00:00', 'work_minutes' => 240]);
        Livewire::test(ListAttendanceResults::class)->assertSee('00123')->assertSee('8:00:00')
            ->searchTable('00123')->assertCanSeeTableRecords([$row])->assertCanNotSeeTableRecords([$other])
            ->searchTable('8:00:00')->assertCanSeeTableRecords([$row])->assertCanNotSeeTableRecords([$other]);
    }

    public function test_employees_can_be_found_by_department_name(): void
    {
        $this->admin();
        $department = Department::create(['code' => 'OPS', 'name' => 'Operations', 'is_active' => true]);
        $employee = Employee::create(['department_id' => $department->id, 'name' => 'Sample', 'is_active' => true]);
        $other = Employee::create(['name' => 'Other', 'is_active' => true]);
        Livewire::test(ListEmployees::class)->assertSee('Operations')->searchTable('Operations')
            ->assertCanSeeTableRecords([$employee])->assertCanNotSeeTableRecords([$other]);
    }
}
