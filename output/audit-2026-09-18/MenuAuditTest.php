<?php

namespace Tests\Audit;

use App\Filament\Resources\AttendanceResults\Pages\CreateAttendanceResult;
use App\Filament\Resources\AttendanceResults\Pages\ListAttendanceResults;
use App\Filament\Resources\DailyActivities\Pages\CreateDailyActivity;
use App\Filament\Resources\Departments\Pages\CreateDepartment;
use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\MeetingBookings\Pages\CreateMeetingBooking;
use App\Filament\Resources\Reminders\Pages\CreateReminder;
use App\Filament\Resources\TicketCategories\Pages\CreateTicketCategory;
use App\Filament\Resources\Tickets\Pages\CreateTicket;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\VehicleBookings\Pages\CreateVehicleBooking;
use App\Models\ActivityCategory;
use App\Models\AttendanceImport;
use App\Models\AttendanceResult;
use App\Models\Department;
use App\Models\Employee;
use App\Models\MeetingRoom;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkHourRecord;
use Database\Seeders\AccessControlSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Audit probes: run with the repository phpunit.xml; never run against real data. */
class MenuAuditTest extends TestCase
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

    public static function masters(): array
    {
        return [
            'Department' => ['Departments', 'Department', ['code' => 'AUDIT', 'name' => 'Audit Department', 'is_active' => true]],
            'Employee' => ['Employees', 'Employee', ['name' => 'Audit Employee', 'employee_no' => 'AUDIT-E', 'email' => 'audit@example.test', 'is_active' => true]],
            'Permit company' => ['PermitCompanies', 'PermitCompany', ['code' => 'AUDIT', 'name' => 'Audit Company', 'is_active' => true]],
            'Project' => ['WorkProjects', 'WorkProject', ['code' => 'AUDIT', 'name' => 'Audit Project', 'status' => 'active', 'start_date' => '2026-09-18', 'end_date' => '2026-09-20']],
            'Activity category' => ['ActivityCategories', 'ActivityCategory', ['code' => 'AUDIT', 'name' => 'Audit Category', 'is_active' => true]],
            'Task category' => ['TaskCategories', 'TaskCategory', ['code' => 'AUDIT', 'name' => 'Audit Task Category', 'is_active' => true]],
            'Meeting room' => ['MeetingRooms', 'MeetingRoom', ['code' => 'AUDIT', 'name' => 'Audit Room', 'capacity' => 8, 'available_from' => '08:00', 'available_until' => '18:00', 'is_active' => true]],
            'Vehicle' => ['Vehicles', 'Vehicle', ['plate_number' => 'B-AUDIT', 'name' => 'Audit Vehicle', 'capacity' => 5, 'available_from' => '06:00', 'available_until' => '22:00', 'is_active' => true]],
            'Work location' => ['WorkLocations', 'WorkLocation', ['gps_name' => 'Audit Location', 'latitude' => -6.2, 'longitude' => 106.8, 'radius_meters' => 50, 'is_flexible' => false, 'is_active' => true]],
        ];
    }

    #[DataProvider('masters')]
    public function test_master_create_edit_delete(string $group, string $singular, array $data): void
    {
        $this->admin();
        $model = 'App\\Models\\'.$singular;
        $base = 'App\\Filament\\Resources\\'.$group.'\\Pages\\';
        Livewire::test($base.'Create'.$singular)->fillForm($data)->call('create')->assertHasNoFormErrors();
        $key = $singular === 'WorkLocation' ? 'gps_name' : 'name';
        $record = $model::query()->where($key, $data[$key])->sole();
        Livewire::test($base.'Edit'.$singular, ['record' => $record->getRouteKey()])
            ->fillForm([$key => 'Audit Updated'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Audit Updated', $record->fresh()->{$key});
        if (! in_array($singular, ['WorkProject', 'ActivityCategory'], true)) {
            Livewire::test($base.'Edit'.$singular, ['record' => $record->getRouteKey()])->callAction('delete');
            $this->assertNull($record->fresh());
        }
    }

    public static function invalidMasters(): array
    {
        return [
            'Project reverse dates' => ['WorkProjects', 'WorkProject', ['code' => 'A', 'name' => 'Audit', 'status' => 'active', 'start_date' => '2026-09-20', 'end_date' => '2026-09-18'], ['end_date']],
            'Invalid coordinates/radius' => ['WorkLocations', 'WorkLocation', ['gps_name' => 'Audit', 'latitude' => 91, 'longitude' => 181, 'radius_meters' => -1, 'is_active' => true], ['latitude', 'longitude', 'radius_meters']],
            'Room zero capacity/reverse hours' => ['MeetingRooms', 'MeetingRoom', ['code' => 'A', 'name' => 'Audit', 'capacity' => 0, 'available_from' => '18:00', 'available_until' => '08:00', 'is_active' => true], ['capacity', 'available_until']],
            'Vehicle zero capacity/reverse hours' => ['Vehicles', 'Vehicle', ['plate_number' => 'A', 'name' => 'Audit', 'capacity' => 0, 'available_from' => '18:00', 'available_until' => '08:00', 'is_active' => true], ['capacity', 'available_until']],
            'Employee malformed email' => ['Employees', 'Employee', ['name' => 'Audit', 'email' => 'invalid-email', 'is_active' => true], ['email']],
        ];
    }

    #[DataProvider('invalidMasters')]
    public function test_invalid_master_inputs_are_rejected(string $group, string $singular, array $data, array $fields): void
    {
        $this->admin();
        Livewire::test('App\\Filament\\Resources\\'.$group.'\\Pages\\Create'.$singular)
            ->fillForm($data)->call('create')->assertHasFormErrors($fields);
    }

    public function test_duplicate_department_is_validation_error_not_server_error(): void
    {
        $this->admin();
        Department::create(['code' => 'AUDIT', 'name' => 'Existing', 'is_active' => true]);
        Livewire::test(CreateDepartment::class)
            ->fillForm(['code' => 'AUDIT', 'name' => 'Duplicate', 'is_active' => true])
            ->call('create')->assertHasFormErrors(['code']);
    }

    public function test_duplicate_employee_account_is_validation_error_not_server_error(): void
    {
        $this->admin();
        $user = User::factory()->create();
        Employee::create(['user_id' => $user->id, 'name' => 'Existing', 'is_active' => true]);
        Livewire::test(CreateEmployee::class)
            ->fillForm(['user_id' => $user->id, 'name' => 'Duplicate', 'is_active' => true])
            ->call('create')->assertHasFormErrors(['user_id']);
    }

    public function test_attendance_negative_duration_is_rejected(): void
    {
        $this->admin();
        Livewire::test(CreateAttendanceResult::class)
            ->fillForm(['employee_name' => 'Audit', 'work_minutes' => -60])
            ->call('create')->assertHasFormErrors(['work_minutes']);
        $this->assertDatabaseCount('attendance_results', 0);
    }

    public function test_attendance_invalid_import_reference_is_validation_error(): void
    {
        $this->admin();
        Livewire::test(CreateAttendanceResult::class)
            ->fillForm(['attendance_import_id' => 999999, 'employee_name' => 'Audit', 'work_minutes' => 60])
            ->call('create')->assertHasFormErrors(['attendance_import_id']);
    }

    public function test_report_results_show_processor_employee_code(): void
    {
        $this->admin();
        AttendanceResult::create(['employee_code' => 'AUDIT-EMP-987', 'employee_name' => 'Audit', 'work_minutes' => 60]);
        $page = Livewire::test(ListAttendanceResults::class);
        $this->assertStringContainsString('AUDIT-EMP-987', strip_tags($page->html()));
    }

    public function test_daily_activity_create_and_overlap_validation(): void
    {
        $this->admin();
        $category = ActivityCategory::create(['code' => 'AUDIT', 'name' => 'Audit', 'is_active' => true]);
        $data = ['work_date' => today()->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00', 'duration_minutes' => 60, 'title' => 'Audit activity', 'source_type' => 'manual', 'work_context' => 'operational', 'activity_category_id' => $category->id, 'requester_type' => 'company', 'requester_company_name' => 'Audit Client'];
        Livewire::test(CreateDailyActivity::class)
            ->fillForm($data)->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('daily_activities', ['title' => 'Audit activity', 'duration_minutes' => 60]);
        Livewire::test(CreateDailyActivity::class)
            ->fillForm($data)->call('create')->assertHasFormErrors();
        $this->assertDatabaseCount('daily_activities', 1);
    }

    public function test_meeting_and_vehicle_forms_save_without_outbound_notifications(): void
    {
        $this->admin();
        $room = MeetingRoom::create(['code' => 'A', 'name' => 'Audit', 'capacity' => 5, 'available_from' => '08:00', 'available_until' => '18:00', 'is_active' => true]);
        Livewire::test(CreateMeetingBooking::class)
            ->fillForm(['title' => 'Audit meeting', 'meeting_room_id' => $room->id, 'meeting_date' => today()->addDay()->toDateString(), 'start_time' => '09:00', 'duration_hours' => '1', 'meeting_type' => 'onsite', 'participants' => []])
            ->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseCount('meeting_bookings', 1);
        $vehicle = Vehicle::create(['plate_number' => 'A', 'name' => 'Audit', 'capacity' => 5, 'available_from' => '06:00', 'available_until' => '22:00', 'is_active' => true]);
        Livewire::test(CreateVehicleBooking::class)
            ->fillForm(['title' => 'Audit trip', 'vehicle_id' => $vehicle->id, 'booking_date' => today()->addDay()->toDateString(), 'start_time' => '09:00', 'duration_hours' => '1', 'destination' => 'Audit', 'passengers_count' => 1])
            ->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseCount('vehicle_bookings', 1);
    }

    public function test_reminder_form_save(): void
    {
        $user = $this->admin();
        $department = Department::create(['code' => 'A', 'name' => 'Audit', 'is_active' => true]);
        $employee = Employee::create(['user_id' => $user->id, 'department_id' => $department->id, 'name' => 'Audit', 'is_active' => true]);
        Livewire::test(CreateReminder::class)
            ->fillForm(['title' => 'Audit reminder', 'reminder_type' => 'general', 'employee_id' => $employee->id, 'department_id' => $department->id, 'reminder_at' => now()->addDay()->format('Y-m-d H:i:s'), 'status' => 'pending', 'email_alarm_days' => []])
            ->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('reminders', ['title' => 'Audit reminder']);
    }

    public function test_guest_cannot_open_any_panel_index(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'panel') && ! str_contains($route->uri(), '{') && in_array('GET', $route->methods()) && ! str_ends_with($route->uri(), '/create')) {
                $this->get('/'.$route->uri())->assertRedirect('/login');
            }
        }
    }

    public function test_unprivileged_user_cannot_open_admin_and_attendance_menus(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        foreach (['departments', 'employees', 'users', 'permit-companies', 'work-projects', 'activity-categories', 'ticket-categories', 'task-categories', 'attendance-imports', 'attendance-results', 'work-locations', 'meeting-rooms', 'vehicles', 'roles', 'location-reports', 'work-hour-imports', 'work-hour-records'] as $slug) {
            $this->get('/panel/'.$slug)->assertForbidden();
        }
    }

    public function test_inactive_employee_cannot_open_panel(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        Employee::create(['user_id' => $user->id, 'name' => 'Inactive audit', 'is_active' => false]);
        $this->actingAs($user)->get('/panel')->assertForbidden();
    }

    public static function listPages(): array
    {
        $groups = ['Departments', 'Employees', 'Users', 'PermitCompanies', 'WorkProjects', 'ActivityCategories', 'AttendanceImports', 'AttendanceResults', 'WorkLocations', 'MeetingBookings', 'MeetingRooms', 'VehicleBookings', 'Vehicles', 'Reminders', 'Tickets', 'TicketCategories', 'WorkTasks', 'DailyActivities', 'TaskCategories', 'ActivityReports'];

        return array_combine($groups, array_map(fn ($group) => [$group], $groups));
    }

    #[DataProvider('listPages')]
    public function test_table_search_and_sort(string $group): void
    {
        $this->admin();
        $page = Livewire::test('App\\Filament\\Resources\\'.$group.'\\Pages\\List'.$group)
            ->assertSuccessful()->searchTable('AUDIT-NOMATCH')->assertSuccessful();
        foreach ($page->instance()->getTable()->getColumns() as $column) {
            if ($column->isSortable()) {
                $page->sortTable($column->getName(), 'desc')->assertSuccessful();
            }
        }
    }

    public function test_attendance_viewer_can_follow_report_center_result_link(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $permission = Permission::create(['code' => 'attendance.view', 'name' => 'View attendance', 'is_active' => true]);
        $user->directPermissions()->attach($permission);
        $import = AttendanceImport::create(['uploaded_by_user_id' => $user->id, 'period_name' => 'Audit period', 'status' => 'processed']);
        $this->actingAs($user)->get('/panel/attendance-report-center')->assertOk();
        $this->get('/panel/attendance-imports/'.$import->id.'/results')->assertOk();
    }

    public function test_ticket_category_and_service_desk_create_work_log(): void
    {
        $user = $this->admin();
        $department = Department::create(['code' => 'AUDIT', 'name' => 'Audit Department', 'is_active' => true]);
        Employee::create(['user_id' => $user->id, 'department_id' => $department->id, 'name' => 'Audit Employee', 'is_active' => true]);
        Livewire::test(CreateTicketCategory::class)
            ->fillForm(['name' => 'Audit request', 'code' => 'AUDIT', 'workflow_type' => 'single', 'handler_department_id' => $department->id, 'is_active' => true, 'requires_permit' => false])
            ->call('create')->assertHasNoFormErrors();
        $category = TicketCategory::where('code', 'AUDIT')->sole();
        Livewire::test(CreateTicket::class)
            ->fillForm(['handler_department_id' => $department->id, 'ticket_category_id' => $category->id, 'subject' => 'Audit Service Desk', 'description' => 'Audit only', 'priority' => 'medium', 'status' => 'open', 'reported_at' => now()->format('Y-m-d H:i:s')])
            ->call('create')->assertHasNoFormErrors();
        $ticket = Ticket::where('subject', 'Audit Service Desk')->sole();
        $this->assertDatabaseHas('work_tasks', ['ticket_id' => $ticket->id, 'department_id' => $department->id]);
    }

    public function test_user_management_create_and_edit_without_changing_password(): void
    {
        $this->admin();
        $this->seed(AccessControlSeeder::class);
        $department = Department::create(['code' => 'AUDIT', 'name' => 'Audit Department', 'is_active' => true]);
        $employee = Employee::create(['department_id' => $department->id, 'name' => 'Audit Account', 'email' => 'audit-account@example.test', 'is_active' => true]);
        $role = Role::where('code', 'requester')->sole();
        Livewire::test(CreateUser::class)
            ->fillForm(['employee_id' => $employee->id, 'name' => 'Audit Account', 'email' => 'audit-account@example.test', 'password' => 'Test-only-audit-2026!', 'is_admin' => false, 'role_ids' => [$role->id], 'additional_access' => [], 'accessibleDepartments' => []])
            ->call('create')->assertHasNoFormErrors();
        $user = User::where('email', 'audit-account@example.test')->sole();
        $hash = $user->password;
        Livewire::test(EditUser::class, ['record' => $user->id])
            ->fillForm(['name' => 'Audit Edited', 'password' => ''])->call('save')->assertHasNoFormErrors();
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertSame($user->id, $employee->fresh()->user_id);
    }

    public function test_real_excel_export_contains_both_sheets_and_expected_values(): void
    {
        $user = $this->admin();
        $import = AttendanceImport::create(['uploaded_by_user_id' => $user->id, 'period_name' => 'Audit period', 'status' => 'processed']);
        AttendanceResult::create(['attendance_import_id' => $import->id, 'employee_code' => '00123', 'employee_name' => 'Audit Employee', 'attendance_date' => '2026-09-18', 'check_time' => '09:00:00', 'duration_text' => '8:00:00', 'work_minutes' => 480]);
        WorkHourRecord::create(['attendance_import_id' => $import->id, 'employee_code' => '00123', 'employee_name' => 'Audit Employee', 'work_minutes' => 480, 'work_hours_text' => '8:00:00']);
        $response = $this->get(route('attendance-imports.download', $import))->assertOk();
        $file = $response->baseResponse->getFile()->getPathname();
        $workbook = IOFactory::load($file);
        $this->assertSame(['Activity Check', 'Work Hour Summary'], $workbook->getSheetNames());
        $this->assertSame('00123', $workbook->getSheet(0)->getCell('D2')->getValue());
        $this->assertSame('00123', $workbook->getSheet(1)->getCell('A2')->getValue());
        $this->assertSame('8:00:00', $workbook->getSheet(1)->getCell('C2')->getValue());
        $workbook->disconnectWorksheets();
    }

    public function test_report_result_employee_code_search_finds_processed_rows(): void
    {
        $this->admin();
        $row = AttendanceResult::create(['employee_code' => 'AUDIT-EMP-987', 'employee_name' => 'Sample Name', 'work_minutes' => 60]);
        Livewire::test(ListAttendanceResults::class)
            ->searchTable('AUDIT-EMP-987')->assertCanSeeTableRecords([$row]);
    }
}
