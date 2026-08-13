<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkTaskStatusGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_receiver_can_cancel_with_reason_and_requester_cannot_cancel(): void
    {
        [$department, $receiver, $ticket, $task] = $this->context();
        $requester = User::factory()->create();
        $requesterEmployee = Employee::query()->create([
            'user_id' => $requester->id,
            'department_id' => Department::query()->create([
                'code' => 'REQ',
                'name' => 'Requester Department',
                'is_active' => true,
            ])->id,
            'employee_no' => 'REQ-001',
            'name' => 'Requester',
            'is_active' => true,
        ]);
        $ticket->update(['employee_id' => $requesterEmployee->id]);

        $this->actingAs($requester);

        try {
            $task->update([
                'status' => 'cancel',
                'status_reason' => 'Requester wants to cancel.',
            ]);
            $this->fail('Requester should not be able to cancel a receiver work log.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        try {
            $task->refresh()->update([
                'status' => 'hold',
                'status_reason' => 'Requester wants to pause.',
            ]);
            $this->fail('Requester should not be able to hold a receiver work log.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->actingAs($receiver);
        $task->refresh()->update([
            'status' => 'cancel',
            'status_reason' => 'Scope is outside the receiving department mandate.',
        ]);

        $this->assertSame('cancel', $task->fresh()->status);
        $this->assertSame(
            'Scope is outside the receiving department mandate.',
            $task->fresh()->status_reason
        );
        $this->assertDatabaseHas('ticket_comments', [
            'work_task_id' => $task->id,
            'user_id' => $receiver->id,
            'activity_type' => 'status_change',
        ]);
    }

    public function test_hold_and_cancel_require_a_reason(): void
    {
        [, $receiver, , $task] = $this->context();
        $this->actingAs($receiver);

        foreach (['hold', 'cancel'] as $status) {
            try {
                $task->refresh()->update([
                    'status' => $status,
                    'status_reason' => null,
                ]);
                $this->fail("{$status} should require a reason.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('status_reason', $exception->errors());
            }
        }
    }

    public function test_service_desk_is_locked_after_receiver_starts_work(): void
    {
        [, $receiver, $ticket, $task] = $this->context();
        $this->actingAs($receiver);

        $this->assertTrue(TicketResource::canEdit($ticket));

        $task->update(['status' => 'in_progress']);

        $this->assertFalse(TicketResource::canEdit($ticket));

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->assertTrue(TicketResource::canEdit($ticket));
    }

    private function context(): array
    {
        $department = Department::query()->create([
            'code' => 'RCV',
            'name' => 'Receiving Department',
            'is_active' => true,
        ]);
        $receiver = User::factory()->create();
        $employee = Employee::query()->create([
            'user_id' => $receiver->id,
            'department_id' => $department->id,
            'employee_no' => 'RCV-001',
            'name' => 'Receiver',
            'is_active' => true,
        ]);
        $role = Role::query()->create([
            'name' => 'Receiving Manager',
            'code' => 'receiving-manager',
            'is_active' => true,
        ]);
        $permissionIds = collect([
            ['name' => 'View Service Desk', 'code' => 'tickets.view'],
            ['name' => 'Manage Service Desk', 'code' => 'tickets.manage'],
            ['name' => 'Manage Work Logs', 'code' => 'worklogs.manage'],
        ])->map(fn (array $data): int => Permission::query()->create([
            ...$data,
            'is_active' => true,
        ])->id);
        $role->permissions()->sync($permissionIds);
        $receiver->roles()->attach($role);
        $ticket = Ticket::query()->create([
            'ticket_no' => 'R STATUS-0001',
            'handler_department_id' => $department->id,
            'subject' => 'Status governance',
            'reported_at' => now(),
        ]);
        $task = WorkTask::query()->create([
            'task_no' => 'T STATUS-0001',
            'ticket_id' => $ticket->id,
            'department_id' => $department->id,
            'employee_id' => $employee->id,
            'title' => 'Governed receiver work',
            'status' => 'planned',
        ]);

        return [$department, $receiver, $ticket, $task];
    }
}
