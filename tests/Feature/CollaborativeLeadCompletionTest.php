<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\User;
use App\Models\WorkTask;
use App\Notifications\TicketResolvedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CollaborativeLeadCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_done_completes_all_collaborative_tasks_and_resolves_the_ticket(): void
    {
        Notification::fake();

        $requesterDepartment = $this->createDepartment('REQ', 'Requester');
        $it = $this->createDepartment('IT', 'Information Technology');
        $hr = $this->createDepartment('HR', 'Human Resources');
        $legal = $this->createDepartment('LGL', 'Legal');

        $requesterUser = User::factory()->create();
        $requester = Employee::query()->create([
            'user_id' => $requesterUser->id,
            'department_id' => $requesterDepartment->id,
            'name' => 'Requester',
            'is_active' => true,
        ]);

        $leadUser = User::factory()->create();
        Employee::query()->create([
            'user_id' => $leadUser->id,
            'department_id' => $it->id,
            'name' => 'IT Lead',
            'is_active' => true,
        ]);

        $ticket = Ticket::query()->create([
            'ticket_no' => 'REQ-202608-0001',
            'employee_id' => $requester->id,
            'requester_department_id' => $requesterDepartment->id,
            'handler_department_id' => $it->id,
            'subject' => 'Collaborative review',
            'workflow_type' => 'collaborative',
            'status' => 'open',
        ]);

        $leadTask = $this->createAssignedTask($ticket, $it, 1);
        $hrTask = $this->createAssignedTask($ticket, $hr, 2);
        $legalTask = $this->createAssignedTask($ticket, $legal, 3);

        $hrTask->updateQuietly([
            'status' => 'done',
            'progress_percent' => 100,
            'completed_at' => now(),
        ]);

        $this->assertFalse($leadTask->canBeCompletedBy($leadUser));
        $this->assertTrue($leadTask->canBeCompletedBy($requesterUser));
        $this->assertFalse($legalTask->canBeCompletedBy($leadUser));
        $this->assertSame(0, (int) $legalTask->progress_percent);

        $this->actingAs($requesterUser);
        $leadTask->update(['status' => 'done']);

        $this->assertSame('done', $leadTask->fresh()->status);
        $this->assertSame(100, (int) $leadTask->fresh()->progress_percent);
        $this->assertSame('done', $hrTask->fresh()->status);
        $this->assertSame('done', $legalTask->fresh()->status);
        $this->assertSame(100, (int) $legalTask->fresh()->progress_percent);
        $this->assertSame('resolved', $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->resolved_at);
        $this->assertSame(
            $requesterUser->id,
            $leadTask->fresh()->completed_by_user_id
        );
        Notification::assertSentTo(
            $requesterUser,
            TicketResolvedNotification::class
        );
    }

    public function test_legacy_requester_lead_work_log_does_not_require_a_pic(): void
    {
        $requesterDepartment = $this->createDepartment('REQ', 'Requester');
        $it = $this->createDepartment('IT', 'Information Technology');
        $requesterUser = User::factory()->create();
        $requester = Employee::query()->create([
            'user_id' => $requesterUser->id,
            'department_id' => $requesterDepartment->id,
            'name' => 'Requester',
            'is_active' => true,
        ]);
        $ticket = Ticket::query()->create([
            'ticket_no' => 'REQ-202608-0099',
            'employee_id' => $requester->id,
            'requester_department_id' => $requesterDepartment->id,
            'handler_department_id' => $it->id,
            'subject' => 'Legacy requester work log',
            'workflow_type' => 'collaborative',
            'status' => 'open',
        ]);
        $primaryTask = $this->createAssignedTask($ticket, $it, 1);
        $requesterTask = WorkTask::query()->create([
            'task_no' => 'TSK-202608-0099',
            'ticket_id' => $ticket->id,
            'department_id' => $requesterDepartment->id,
            'title' => $ticket->subject,
            'status' => 'planned',
        ]);
        TicketAssignment::query()->create([
            'ticket_id' => $ticket->id,
            'department_id' => $requesterDepartment->id,
            'work_task_id' => $requesterTask->id,
            'is_required' => true,
            'sort_order' => 2,
        ]);

        $this->actingAs($requesterUser);
        $primaryTask->update(['status' => 'done']);

        $this->assertSame('done', $requesterTask->fresh()->status);
        $this->assertNull($requesterTask->fresh()->employee_id);
        $this->assertSame('resolved', $ticket->fresh()->status);
    }

    private function createDepartment(string $code, string $name): Department
    {
        return Department::query()->create([
            'code' => $code,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function createAssignedTask(
        Ticket $ticket,
        Department $department,
        int $sortOrder,
    ): WorkTask {
        $pic = Employee::query()->create([
            'department_id' => $department->id,
            'name' => $department->name.' PIC',
            'is_active' => true,
        ]);

        $task = WorkTask::query()->create([
            'task_no' => 'TSK-202608-'.str_pad((string) $sortOrder, 4, '0', STR_PAD_LEFT),
            'ticket_id' => $ticket->id,
            'department_id' => $department->id,
            'employee_id' => $pic->id,
            'title' => $ticket->subject,
            'status' => 'planned',
            'progress_percent' => 0,
        ]);

        TicketAssignment::query()->create([
            'ticket_id' => $ticket->id,
            'department_id' => $department->id,
            'work_task_id' => $task->id,
            'is_required' => true,
            'sort_order' => $sortOrder,
        ]);

        return $task;
    }
}
