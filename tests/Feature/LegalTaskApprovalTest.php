<?php

namespace Tests\Feature;

use App\Filament\Resources\WorkTasks\WorkTaskResource;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\User;
use App\Models\WorkTask;
use App\Notifications\LegalTaskApprovalRequestedNotification;
use App\Notifications\WorkTaskAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LegalTaskApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_can_view_but_cannot_work_on_task_until_cbo_approves_it(): void
    {
        Notification::fake();

        $legal = Department::query()->create([
            'code' => 'LEGAL',
            'name' => 'Legal',
            'is_active' => true,
        ]);

        $legalUser = User::factory()->create();
        Employee::query()->create([
            'user_id' => $legalUser->id,
            'department_id' => $legal->id,
            'employee_no' => 'LEGAL-001',
            'name' => 'Legal Officer',
            'is_active' => true,
        ]);

        $viewerRole = Role::query()->create([
            'name' => 'Legal Viewer',
            'code' => 'legal-viewer',
            'is_active' => true,
        ]);
        $worklogViewPermission = Permission::query()->updateOrCreate(
            ['code' => 'worklogs.view'],
            [
                'name' => 'View Work Logs',
                'module' => 'Work Logs',
                'is_active' => true,
            ]
        );
        $viewerRole->permissions()->attach(
            $worklogViewPermission
        );
        $legalUser->roles()->attach($viewerRole);

        $cbo = User::factory()->create();
        $cbo->roles()->attach(
            Role::query()->where('code', 'cbo')->value('id')
        );

        $task = WorkTask::query()->create([
            'task_no' => 'TSK-LEGAL-001',
            'department_id' => $legal->id,
            'title' => 'Review contract',
            'status' => 'planned',
        ]);

        $this->assertSame('pending', $task->approval_status);
        Notification::assertSentTo(
            $cbo,
            LegalTaskApprovalRequestedNotification::class
        );
        Notification::assertNotSentTo($legalUser, WorkTaskAssignedNotification::class);

        $this->actingAs($legalUser);
        $this->assertSame(1, WorkTaskResource::getEloquentQuery()->count());
        $this->assertTrue(WorkTaskResource::canView($task));
        $this->assertFalse(WorkTaskResource::canEdit($task));
        $this->assertFalse($task->canBeClaimedBy($legalUser));
        $this->assertFalse($task->canBeCompletedBy($legalUser));

        $this->actingAs($cbo);
        $this->assertSame(1, WorkTaskResource::getEloquentQuery()->count());
        $this->assertTrue($task->canBeApprovedBy($cbo));

        $task->approveLegalTask($cbo);

        $task->refresh();
        $this->assertSame('approved', $task->approval_status);
        $this->assertSame($cbo->id, $task->approved_by_user_id);
        $this->assertNotNull($task->approved_at);
        Notification::assertSentTo($legalUser, WorkTaskAssignedNotification::class);

        $this->actingAs($legalUser);
        $this->assertSame(1, WorkTaskResource::getEloquentQuery()->count());
        $this->assertTrue(WorkTaskResource::canView($task));
    }

    public function test_pending_legal_task_notifies_only_active_legal_approvers(): void
    {
        Notification::fake();

        $legal = Department::query()->create([
            'code' => 'LG-NOTIFY',
            'name' => 'Legal',
            'is_active' => true,
        ]);
        $activeCbo = User::factory()->create();
        $activeCbo->roles()->attach(Role::query()->where('code', 'cbo')->value('id'));
        $inactiveCbo = User::factory()->create();
        $inactiveCbo->roles()->attach(Role::query()->where('code', 'cbo')->value('id'));
        Employee::query()->create([
            'user_id' => $inactiveCbo->id,
            'department_id' => $legal->id,
            'employee_no' => 'CBO-INACTIVE',
            'name' => 'Inactive CBO',
            'is_active' => false,
        ]);
        $regularUser = User::factory()->create();

        WorkTask::query()->create([
            'task_no' => 'T LEGAL-NOTIFY-0001',
            'department_id' => $legal->id,
            'title' => 'Legal work awaiting approval',
            'status' => 'planned',
        ]);

        Notification::assertSentTo(
            $activeCbo,
            LegalTaskApprovalRequestedNotification::class
        );
        Notification::assertNotSentTo(
            [$inactiveCbo, $regularUser],
            LegalTaskApprovalRequestedNotification::class
        );
    }

    public function test_non_legal_task_does_not_require_cbo_approval(): void
    {
        $operations = Department::query()->create([
            'code' => 'OPS',
            'name' => 'Operations',
            'is_active' => true,
        ]);

        $task = WorkTask::query()->create([
            'task_no' => 'TSK-OPS-001',
            'department_id' => $operations->id,
            'title' => 'Routine task',
            'status' => 'planned',
        ]);

        $this->assertNull($task->approval_status);
        $this->assertFalse($task->isAwaitingLegalApproval());
    }

    public function test_system_administrator_role_can_approve_legal_tasks(): void
    {
        $legal = Department::query()->create([
            'code' => 'LG',
            'name' => 'Legal',
            'is_active' => true,
        ]);
        $systemAdmin = User::factory()->create(['is_admin' => false]);
        $systemAdminRole = Role::query()->updateOrCreate(
            ['code' => 'system-admin'],
            [
                'name' => 'System Administrator',
                'is_active' => true,
            ]
        );
        $systemAdmin->roles()->attach($systemAdminRole);
        $task = WorkTask::query()->create([
            'task_no' => 'TSK-LEGAL-ADMIN',
            'department_id' => $legal->id,
            'title' => 'Legal approval by system administrator',
            'status' => 'planned',
        ]);

        $this->actingAs($systemAdmin);

        $this->assertTrue($systemAdmin->hasPermission('legal-tasks.approve'));
        $this->assertTrue($task->canBeApprovedBy($systemAdmin));

        $this->assertTrue($task->isAwaitingLegalApproval());
    }

    public function test_collaborative_request_only_holds_the_legal_work_log(): void
    {
        $legal = Department::query()->create([
            'code' => 'LG',
            'name' => 'Legal',
            'is_active' => true,
        ]);
        $engineering = Department::query()->create([
            'code' => 'ENG',
            'name' => 'Engineering',
            'is_active' => true,
        ]);
        $finance = Department::query()->create([
            'code' => 'FIN',
            'name' => 'Finance',
            'is_active' => true,
        ]);
        $ticket = Ticket::query()->create([
            'ticket_no' => 'R COLLAB-0001',
            'requester_department_id' => $finance->id,
            'handler_department_id' => $engineering->id,
            'subject' => 'Collaborative request with Legal reviewer',
            'workflow_type' => 'collaborative',
        ]);
        $engineeringTask = WorkTask::query()->create([
            'task_no' => 'T COLLAB-ENG-0001',
            'ticket_id' => $ticket->id,
            'department_id' => $engineering->id,
            'title' => 'Engineering work',
            'status' => 'planned',
        ]);
        $legalTask = WorkTask::query()->create([
            'task_no' => 'T COLLAB-LG-0001',
            'ticket_id' => $ticket->id,
            'department_id' => $legal->id,
            'title' => 'Legal review',
            'status' => 'planned',
        ]);

        TicketAssignment::query()->create([
            'ticket_id' => $ticket->id,
            'department_id' => $engineering->id,
            'work_task_id' => $engineeringTask->id,
            'is_required' => true,
            'sort_order' => 1,
        ]);
        TicketAssignment::query()->create([
            'ticket_id' => $ticket->id,
            'department_id' => $legal->id,
            'work_task_id' => $legalTask->id,
            'is_required' => true,
            'sort_order' => 2,
        ]);

        $this->assertNull($engineeringTask->approval_status);
        $this->assertFalse($engineeringTask->isAwaitingLegalApproval());
        $this->assertSame('pending', $legalTask->approval_status);
        $this->assertTrue($legalTask->isAwaitingLegalApproval());
    }

    public function test_request_from_legal_to_another_department_needs_no_approval(): void
    {
        $legal = Department::query()->create([
            'code' => 'LG',
            'name' => 'Legal',
            'is_active' => true,
        ]);
        $engineering = Department::query()->create([
            'code' => 'ENG',
            'name' => 'Engineering',
            'is_active' => true,
        ]);
        $ticket = Ticket::query()->create([
            'ticket_no' => 'R FROM-LEGAL-0001',
            'requester_department_id' => $legal->id,
            'handler_department_id' => $engineering->id,
            'subject' => 'Request from Legal to Engineering',
            'workflow_type' => 'collaborative',
        ]);
        $engineeringTask = WorkTask::query()->create([
            'task_no' => 'T FROM-LEGAL-0001',
            'ticket_id' => $ticket->id,
            'department_id' => $engineering->id,
            'title' => 'Engineering work requested by Legal',
            'status' => 'planned',
        ]);

        $this->assertNull($engineeringTask->approval_status);
        $this->assertFalse($engineeringTask->isAwaitingLegalApproval());
    }

    public function test_cbo_can_reject_primary_legal_task_with_required_reason(): void
    {
        $legal = Department::query()->create([
            'code' => 'LG',
            'name' => 'Legal',
            'is_active' => true,
        ]);
        $cbo = User::factory()->create();
        $cbo->roles()->attach(Role::query()->where('code', 'cbo')->value('id'));
        $ticket = Ticket::query()->create([
            'ticket_no' => 'R LEGAL-REJECT-0001',
            'handler_department_id' => $legal->id,
            'subject' => 'Primary Legal request to reject',
            'workflow_type' => 'single',
        ]);
        $task = WorkTask::query()->create([
            'task_no' => 'T LEGAL-REJECT-0001',
            'ticket_id' => $ticket->id,
            'department_id' => $legal->id,
            'title' => 'Legal work requiring decision',
            'status' => 'planned',
        ]);

        $this->actingAs($cbo);
        $task->rejectLegalTask($cbo, 'Dokumen dasar belum lengkap.');

        $task->refresh();
        $this->assertSame('rejected', $task->approval_status);
        $this->assertSame('planned', $task->status);
        $this->assertSame('Dokumen dasar belum lengkap.', $task->rejection_reason);
        $this->assertSame($cbo->id, $task->rejected_by_user_id);
        $this->assertNotNull($task->rejected_at);
        $this->assertSame('rejected', $ticket->fresh()->status);
        $this->assertSame(
            'Legal approval rejected: Dokumen dasar belum lengkap.',
            $ticket->fresh()->resolution_notes
        );
        $this->assertFalse($task->canBeManagedBy($cbo));
        $this->assertDatabaseHas('ticket_comments', [
            'work_task_id' => $task->id,
            'activity_type' => 'legal_approval_rejected',
            'user_id' => $cbo->id,
        ]);
    }

    public function test_requester_can_revise_and_resubmit_the_same_rejected_legal_task(): void
    {
        $legal = Department::query()->create([
            'code' => 'LG-RESUBMIT',
            'name' => 'Legal',
            'is_active' => true,
        ]);
        $requester = User::factory()->create();
        $requesterEmployee = Employee::query()->create([
            'user_id' => $requester->id,
            'department_id' => $legal->id,
            'employee_no' => 'REQUESTER-LEGAL-REVISION',
            'name' => 'Legal Requester',
            'is_active' => true,
        ]);
        $cbo = User::factory()->create();
        $cbo->roles()->attach(Role::query()->where('code', 'cbo')->value('id'));
        $ticket = Ticket::query()->create([
            'ticket_no' => 'R LEGAL-RESUBMIT-0001',
            'employee_id' => $requesterEmployee->id,
            'handler_department_id' => $legal->id,
            'subject' => 'Legal request to revise',
            'workflow_type' => 'single',
        ]);
        $task = WorkTask::query()->create([
            'task_no' => 'T LEGAL-RESUBMIT-0001',
            'ticket_id' => $ticket->id,
            'department_id' => $legal->id,
            'title' => 'Same Legal task',
            'status' => 'planned',
        ]);

        $this->actingAs($cbo);
        $task->rejectLegalTask($cbo, 'Mohon lengkapi latar belakang.');

        Notification::fake();
        $this->actingAs($requester);
        $task->resubmitLegalTask($requester);

        $task->refresh();
        $this->assertSame('T LEGAL-RESUBMIT-0001', $task->task_no);
        $this->assertSame('pending', $task->approval_status);
        $this->assertSame('planned', $task->status);
        $this->assertNull($task->rejection_reason);
        $this->assertSame('open', $ticket->fresh()->status);
        $this->assertDatabaseHas('ticket_comments', [
            'work_task_id' => $task->id,
            'activity_type' => 'legal_approval_resubmitted',
            'user_id' => $requester->id,
        ]);
        Notification::assertSentTo(
            $cbo,
            LegalTaskApprovalRequestedNotification::class
        );
    }

    public function test_cbo_cannot_reject_legal_task_without_reason(): void
    {
        $legal = Department::query()->create([
            'code' => 'LG',
            'name' => 'Legal',
            'is_active' => true,
        ]);
        $cbo = User::factory()->create();
        $cbo->roles()->attach(Role::query()->where('code', 'cbo')->value('id'));
        $task = WorkTask::query()->create([
            'task_no' => 'T LEGAL-REJECT-NO-REASON',
            'department_id' => $legal->id,
            'title' => 'Legal rejection must have reason',
            'status' => 'planned',
        ]);

        $this->expectException(ValidationException::class);

        $task->rejectLegalTask($cbo, '   ');
    }

    public function test_cbo_keeps_approved_and_rejected_legal_tasks_as_decision_history(): void
    {
        $legal = Department::query()->create([
            'code' => 'LG',
            'name' => 'Legal',
            'is_active' => true,
        ]);
        $cbo = User::factory()->create();
        $cbo->roles()->attach(Role::query()->where('code', 'cbo')->value('id'));
        $pendingTask = WorkTask::query()->create([
            'task_no' => 'T LEGAL-HISTORY-PENDING',
            'department_id' => $legal->id,
            'title' => 'Pending decision',
            'status' => 'planned',
        ]);
        $approvedTask = WorkTask::query()->create([
            'task_no' => 'T LEGAL-HISTORY-APPROVED',
            'department_id' => $legal->id,
            'title' => 'Approved decision',
            'status' => 'planned',
        ]);
        $rejectedTask = WorkTask::query()->create([
            'task_no' => 'T LEGAL-HISTORY-REJECTED',
            'department_id' => $legal->id,
            'title' => 'Rejected decision',
            'status' => 'planned',
        ]);

        $this->actingAs($cbo);
        $approvedTask->approveLegalTask($cbo);
        $rejectedTask->rejectLegalTask($cbo, 'Tidak sesuai kebijakan bisnis.');

        $visibleTaskIds = WorkTaskResource::getEloquentQuery()
            ->pluck('work_tasks.id')
            ->all();

        $this->assertContains($pendingTask->id, $visibleTaskIds);
        $this->assertContains($approvedTask->id, $visibleTaskIds);
        $this->assertContains($rejectedTask->id, $visibleTaskIds);
        $this->assertTrue(WorkTaskResource::canView($approvedTask->fresh()));
        $this->assertTrue(WorkTaskResource::canView($rejectedTask->fresh()));
    }

    public function test_rejected_legal_reviewer_does_not_stop_other_collaborative_departments(): void
    {
        $legal = Department::query()->create([
            'code' => 'LG',
            'name' => 'Legal',
            'is_active' => true,
        ]);
        $engineering = Department::query()->create([
            'code' => 'ENG',
            'name' => 'Engineering',
            'is_active' => true,
        ]);
        $cbo = User::factory()->create();
        $cbo->roles()->attach(Role::query()->where('code', 'cbo')->value('id'));
        $ticket = Ticket::query()->create([
            'ticket_no' => 'R LEGAL-REVIEWER-REJECT-0001',
            'handler_department_id' => $engineering->id,
            'subject' => 'Collaborative request with optional Legal decision',
            'workflow_type' => 'collaborative',
        ]);
        $engineeringTask = WorkTask::query()->create([
            'task_no' => 'T ENG-CONTINUE-0001',
            'ticket_id' => $ticket->id,
            'department_id' => $engineering->id,
            'title' => 'Engineering work continues',
            'status' => 'planned',
        ]);
        $legalTask = WorkTask::query()->create([
            'task_no' => 'T LEGAL-REVIEWER-REJECT-0001',
            'ticket_id' => $ticket->id,
            'department_id' => $legal->id,
            'title' => 'Legal reviewer work',
            'status' => 'planned',
        ]);
        TicketAssignment::query()->create([
            'ticket_id' => $ticket->id,
            'department_id' => $engineering->id,
            'work_task_id' => $engineeringTask->id,
            'is_required' => true,
            'sort_order' => 1,
        ]);
        $legalAssignment = TicketAssignment::query()->create([
            'ticket_id' => $ticket->id,
            'department_id' => $legal->id,
            'work_task_id' => $legalTask->id,
            'is_required' => true,
            'sort_order' => 2,
        ]);

        $this->actingAs($cbo);
        $legalTask->rejectLegalTask($cbo, 'Review Legal tidak diperlukan untuk scope ini.');

        $this->assertSame('rejected', $legalTask->fresh()->approval_status);
        $this->assertSame('planned', $legalTask->fresh()->status);
        $this->assertTrue($legalAssignment->fresh()->is_required);
        $this->assertSame('planned', $engineeringTask->fresh()->status);
        $this->assertSame('rejected', $ticket->fresh()->status);
    }
}
