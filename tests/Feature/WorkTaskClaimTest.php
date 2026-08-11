<?php

namespace Tests\Feature;

use App\Filament\Resources\WorkTasks\WorkTaskResource;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkTaskClaimTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_department_user_can_claim_unassigned_collaborative_task(): void
    {
        $department = $this->department('IT');
        $user = $this->viewer($department);
        $ticket = Ticket::query()->create([
            'ticket_no' => 'REQ-202608-8701',
            'handler_department_id' => $department->id,
            'subject' => 'Collaborative request',
            'workflow_type' => 'collaborative',
        ]);
        $task = WorkTask::query()->create([
            'task_no' => 'TSK-202608-8701',
            'ticket_id' => $ticket->id,
            'department_id' => $department->id,
            'title' => $ticket->subject,
            'status' => 'planned',
        ]);

        $this->actingAs($user);

        $this->assertTrue($task->canBeClaimedBy($user));

        $task->update(['employee_id' => $user->employee->id]);

        $this->assertTrue($task->fresh()->isAssignedTo($user));
        $this->assertTrue(WorkTaskResource::canEdit($task->fresh()));
    }

    public function test_regular_user_cannot_claim_task_from_another_department(): void
    {
        $it = $this->department('IT');
        $tax = $this->department('TAX');
        $user = $this->viewer($it);
        $task = WorkTask::query()->create([
            'task_no' => 'TSK-202608-8702',
            'department_id' => $tax->id,
            'title' => 'Tax task',
            'status' => 'planned',
        ]);

        $this->assertFalse($task->canBeClaimedBy($user));
    }

    private function department(string $code): Department
    {
        return Department::query()->create([
            'code' => $code,
            'name' => $code,
            'is_active' => true,
        ]);
    }

    private function viewer(Department $department): User
    {
        $user = User::factory()->create(['is_admin' => false]);
        Employee::query()->create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'name' => 'Regular User',
            'is_active' => true,
        ]);
        $permission = Permission::query()->create([
            'name' => 'View Work Logs',
            'code' => 'worklogs.view',
            'is_active' => true,
        ]);
        $role = Role::query()->create([
            'name' => 'Requester',
            'code' => 'requester',
            'is_active' => true,
        ]);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
