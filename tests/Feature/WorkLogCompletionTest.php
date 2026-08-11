<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkLogCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_complete_assigned_task_and_actor_is_recorded(): void
    {
        [$requesterUser, $ticket, $handlerDepartment] = $this->ticketContext();
        $pic = Employee::query()->create([
            'department_id' => $handlerDepartment->id,
            'name' => 'IT Executor',
            'is_active' => true,
        ]);
        $task = $this->workTask($ticket, $handlerDepartment, $pic);

        $this->actingAs($requesterUser);

        $this->assertTrue($task->canBeCompletedBy($requesterUser));

        $task->update(['status' => 'done']);

        $task->refresh();
        $this->assertSame('done', $task->status);
        $this->assertSame($pic->id, $task->employee_id);
        $this->assertSame($requesterUser->id, $task->completed_by_user_id);
        $this->assertNotNull($task->completed_at);
    }

    public function test_task_cannot_be_completed_until_destination_department_assigns_a_pic(): void
    {
        [$requesterUser, $ticket, $handlerDepartment] = $this->ticketContext();
        $task = $this->workTask($ticket, $handlerDepartment);

        $this->actingAs($requesterUser);

        try {
            $task->update(['status' => 'done']);
            $this->fail('Completion without a PIC should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('employee_id', $exception->errors());
        }

        $this->assertSame('planned', $task->fresh()->status);
        $this->assertNull($task->fresh()->completed_by_user_id);
    }

    private function ticketContext(): array
    {
        $requesterDepartment = Department::query()->create([
            'code' => 'LGL',
            'name' => 'Legal',
            'is_active' => true,
        ]);
        $handlerDepartment = Department::query()->create([
            'code' => 'IT',
            'name' => 'Information Technology',
            'is_active' => true,
        ]);
        $requesterUser = User::factory()->create();
        $requester = Employee::query()->create([
            'user_id' => $requesterUser->id,
            'department_id' => $requesterDepartment->id,
            'name' => 'Syila',
            'is_active' => true,
        ]);
        $ticket = Ticket::query()->create([
            'ticket_no' => 'REQ-202608-8001',
            'employee_id' => $requester->id,
            'requester_department_id' => $requesterDepartment->id,
            'handler_department_id' => $handlerDepartment->id,
            'subject' => 'Legal request to IT',
            'workflow_type' => 'single',
            'status' => 'open',
        ]);

        return [$requesterUser, $ticket, $handlerDepartment];
    }

    private function workTask(
        Ticket $ticket,
        Department $department,
        ?Employee $pic = null
    ): WorkTask {
        return WorkTask::query()->create([
            'task_no' => 'TSK-202608-8001',
            'ticket_id' => $ticket->id,
            'department_id' => $department->id,
            'employee_id' => $pic?->id,
            'title' => $ticket->subject,
            'status' => 'planned',
            'progress_percent' => 0,
        ]);
    }
}
