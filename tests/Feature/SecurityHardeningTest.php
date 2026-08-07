<?php

namespace Tests\Feature;

use App\Exports\AttendanceReportExport;
use App\Filament\Resources\Tickets\Pages\CreateTicket;
use App\Models\AttendanceImport;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Models\WorkHourRecord;
use App\Models\WorkTask;
use App\Models\WorkTaskFinding;
use App\Notifications\TicketCreatedNotification;
use App\Policies\TicketCommentPolicy;
use App\Policies\WorkTaskFindingPolicy;
use App\Services\AttendanceReportProcessor;
use App\Services\DocumentNumberGenerator;
use App\Services\RoleAssignmentService;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_cannot_access_legacy_department_management(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get('/admin/departments')
            ->assertRedirect('/panel');
    }

    public function test_superadmin_can_access_legacy_department_management(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/admin/departments')
            ->assertOk();
    }

    public function test_regular_user_cannot_download_an_attendance_report(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $import = AttendanceImport::query()->create([
            'uploaded_by_user_id' => $user->id,
            'period_name' => 'August 2026',
            'status' => 'processed',
        ]);

        $this->actingAs($user)
            ->get(route('attendance-imports.download', $import))
            ->assertForbidden();
    }

    public function test_superadmin_can_download_an_attendance_report(): void
    {
        Excel::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $import = AttendanceImport::query()->create([
            'uploaded_by_user_id' => $admin->id,
            'period_name' => 'August 2026',
            'status' => 'processed',
        ]);

        $this->actingAs($admin)
            ->get(route('attendance-imports.download', $import))
            ->assertOk();

        Excel::matchByRegex();
        Excel::assertDownloaded(
            '/attendance-report-august-2026\.xlsx/',
            fn (AttendanceReportExport $export): bool => true
        );
    }

    public function test_attendance_viewer_can_download_an_attendance_report(): void
    {
        Excel::fake();

        $viewer = User::factory()->create(['is_admin' => false]);
        $permission = Permission::query()->create([
            'name' => 'View Attendance',
            'code' => 'attendance.view',
            'is_active' => true,
        ]);
        $role = Role::query()->create([
            'name' => 'Attendance Viewer',
            'code' => 'attendance-viewer',
            'is_active' => true,
        ]);
        $role->permissions()->attach($permission);
        $viewer->roles()->attach($role);
        $import = AttendanceImport::query()->create([
            'uploaded_by_user_id' => $viewer->id,
            'period_name' => 'August 2026',
            'status' => 'processed',
        ]);

        $this->actingAs($viewer)
            ->get(route('attendance-imports.download', $import))
            ->assertOk();
    }

    public function test_user_manager_cannot_assign_a_role_with_extra_permissions(): void
    {
        $actor = User::factory()->create(['is_admin' => false]);
        $manageUsers = Permission::query()->create([
            'name' => 'Manage Users',
            'code' => 'users.manage',
            'is_active' => true,
        ]);
        $manageAttendance = Permission::query()->create([
            'name' => 'Manage Attendance',
            'code' => 'attendance.manage',
            'is_active' => true,
        ]);
        $userManager = Role::query()->create([
            'name' => 'User Manager',
            'code' => 'user-manager',
            'is_active' => true,
        ]);
        $attendanceOperator = Role::query()->create([
            'name' => 'Attendance Operator',
            'code' => 'attendance-operator',
            'is_active' => true,
        ]);
        $userManager->permissions()->attach($manageUsers);
        $attendanceOperator->permissions()->attach($manageAttendance);
        $actor->roles()->attach($userManager);

        $allowedIds = app(RoleAssignmentService::class)
            ->filterAssignableRoleIds($actor, [
                $userManager->id,
                $attendanceOperator->id,
            ]);

        $this->assertSame([$userManager->id], $allowedIds);
    }

    public function test_admin_seeder_skips_creation_without_explicit_credentials(): void
    {
        config()->set('workdesk.bootstrap_admin', [
            'name' => null,
            'email' => null,
            'password' => null,
        ]);

        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_ticket_comment_attachment_requires_ticket_access(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create(['is_admin' => true]);
        $regularUser = User::factory()->create(['is_admin' => false]);
        $ticket = Ticket::query()->create([
            'ticket_no' => 'REQ-202608-9001',
            'subject' => 'Private attachment test',
        ]);
        $comment = TicketComment::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'message' => 'Private document',
            'attachments' => ['ticket-comments/private-report.pdf'],
        ]);
        Storage::disk('local')->put(
            'ticket-comments/private-report.pdf',
            'private-content'
        );

        $url = route(
            'ticket-comments.attachments.download',
            [$comment, 0]
        );

        $this->actingAs($regularUser)
            ->get($url)
            ->assertForbidden();

        $this->actingAs($owner)
            ->get($url)
            ->assertOk()
            ->assertDownload('private-report.pdf');
    }

    public function test_failed_attendance_reprocess_preserves_previous_results(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $import = AttendanceImport::query()->create([
            'uploaded_by_user_id' => $admin->id,
            'period_name' => 'August 2026',
            'status' => 'processed',
            'processed_at' => now(),
        ]);
        $previousResult = WorkHourRecord::query()->create([
            'attendance_import_id' => $import->id,
            'employee_name' => 'Existing Employee',
            'work_minutes' => 480,
        ]);

        try {
            app(AttendanceReportProcessor::class)->process($import);
            $this->fail('The invalid reprocess should have failed.');
        } catch (RuntimeException) {
            // Expected: no replacement files were provided.
        }

        $this->assertDatabaseHas('work_hour_records', [
            'id' => $previousResult->id,
            'work_minutes' => 480,
        ]);
        $this->assertSame('failed', $import->fresh()->status);
    }

    public function test_ticket_created_email_only_goes_to_users_with_ticket_access(): void
    {
        Notification::fake();

        $department = Department::query()->create([
            'code' => 'SEC',
            'name' => 'Security',
            'is_active' => true,
        ]);
        $authorizedUser = User::factory()->create();
        $unauthorizedUser = User::factory()->create();

        foreach ([$authorizedUser, $unauthorizedUser] as $index => $user) {
            Employee::query()->create([
                'user_id' => $user->id,
                'department_id' => $department->id,
                'employee_no' => 'EMP-'.($index + 1),
                'name' => $user->name,
                'is_active' => true,
            ]);
        }

        $permission = Permission::query()->create([
            'name' => 'View Service Desk',
            'code' => 'tickets.view',
            'module' => 'Service Desk',
            'is_active' => true,
        ]);
        $role = Role::query()->create([
            'name' => 'Ticket Viewer',
            'code' => 'ticket-viewer',
            'is_active' => true,
        ]);
        $role->permissions()->attach($permission);
        $authorizedUser->roles()->attach($role);

        Ticket::query()->create([
            'ticket_no' => 'REQ-202608-9002',
            'handler_department_id' => $department->id,
            'subject' => 'Restricted notification',
        ]);

        Notification::assertSentTo(
            $authorizedUser,
            TicketCreatedNotification::class
        );
        Notification::assertNotSentTo(
            $unauthorizedUser,
            TicketCreatedNotification::class
        );
    }

    public function test_ticket_comment_policy_follows_parent_ticket_access(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $regularUser = User::factory()->create(['is_admin' => false]);
        $ticket = Ticket::query()->create([
            'ticket_no' => 'REQ-202608-9003',
            'subject' => 'Policy test',
        ]);
        $comment = TicketComment::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $regularUser->id,
            'message' => 'Restricted comment',
        ]);
        $policy = app(TicketCommentPolicy::class);

        $this->assertFalse($policy->viewAny($regularUser));
        $this->assertFalse($policy->create($regularUser));
        $this->assertFalse($policy->view($regularUser, $comment));
        $this->assertTrue($policy->view($admin, $comment));
    }

    public function test_document_numbers_are_reserved_from_a_database_sequence(): void
    {
        $generator = app(DocumentNumberGenerator::class);
        $prefix = 'REQ-202608-';

        $this->assertSame('REQ-202608-0001', $generator->next($prefix));
        $this->assertSame('REQ-202608-0002', $generator->next($prefix));
        $this->assertDatabaseHas('document_sequences', [
            'prefix' => $prefix,
            'next_number' => 3,
        ]);
    }

    public function test_ticket_and_generated_work_logs_are_created_atomically(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $department = Department::query()->create([
            'code' => 'ATM',
            'name' => 'Atomic Department',
            'is_active' => true,
        ]);
        $this->actingAs($admin);

        $page = new CreateTicket;
        $reviewerDepartments = new \ReflectionProperty(
            CreateTicket::class,
            'reviewerDepartmentIds'
        );
        $reviewerDepartments->setValue($page, [999999]);
        $createRecord = new \ReflectionMethod(
            CreateTicket::class,
            'handleRecordCreation'
        );

        try {
            $createRecord->invoke($page, [
                'ticket_no' => 'REQ-202608-9004',
                'handler_department_id' => $department->id,
                'subject' => 'Atomic creation test',
                'workflow_type' => 'collaborative',
                'status' => 'open',
                'priority' => 'medium',
            ]);
            $this->fail('An invalid reviewer department should fail creation.');
        } catch (QueryException) {
            // Expected foreign-key failure while generating reviewer Work Log.
        }

        $this->assertDatabaseMissing('tickets', [
            'ticket_no' => 'REQ-202608-9004',
        ]);
        $this->assertDatabaseCount('work_tasks', 0);
        $this->assertDatabaseCount('ticket_assignments', 0);
    }

    public function test_primary_ticket_attachment_requires_ticket_access(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['is_admin' => true]);
        $regularUser = User::factory()->create(['is_admin' => false]);
        $ticket = Ticket::query()->create([
            'ticket_no' => 'REQ-202608-9005',
            'subject' => 'Protected primary attachment',
            'attachments' => ['service-request-attachments/private.pdf'],
        ]);
        Storage::disk('local')->put(
            'service-request-attachments/private.pdf',
            'private-content'
        );
        $url = route('tickets.attachments.download', [$ticket, 0]);

        $this->actingAs($regularUser)
            ->get($url)
            ->assertForbidden();

        $this->actingAs($admin)
            ->get($url)
            ->assertOk()
            ->assertDownload('private.pdf');
    }

    public function test_finding_policy_follows_its_parent_work_log(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $regularUser = User::factory()->create(['is_admin' => false]);
        $ticket = Ticket::query()->create([
            'ticket_no' => 'REQ-202608-9006',
            'subject' => 'Finding policy test',
        ]);
        $workTask = WorkTask::query()->create([
            'task_no' => 'TSK-202608-9006',
            'ticket_id' => $ticket->id,
            'title' => 'Finding policy test',
        ]);
        $finding = WorkTaskFinding::query()->create([
            'finding_no' => 'FND-202608-9006',
            'work_task_id' => $workTask->id,
            'title' => 'Restricted finding',
            'description' => 'Sensitive finding',
        ]);
        $policy = app(WorkTaskFindingPolicy::class);

        $this->assertFalse($policy->viewAny($regularUser));
        $this->assertFalse($policy->create($regularUser));
        $this->assertFalse($policy->view($regularUser, $finding));
        $this->assertTrue($policy->view($admin, $finding));
    }

    public function test_sensitive_legacy_uploads_can_be_migrated_safely(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('public')->put(
            'attendance-imports/legacy.xlsx',
            'legacy-content'
        );

        $this->assertSame(
            0,
            Artisan::call('storage:migrate-private-uploads')
        );
        Storage::disk('local')->assertExists(
            'attendance-imports/legacy.xlsx'
        );
        Storage::disk('public')->assertMissing(
            'attendance-imports/legacy.xlsx'
        );
        $this->assertSame(
            'legacy-content',
            Storage::disk('local')->get(
                'attendance-imports/legacy.xlsx'
            )
        );
    }
}
