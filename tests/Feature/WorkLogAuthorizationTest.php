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
use App\Services\WorkTaskMutationGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkLogAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_collaborative_department_only_lists_its_own_work_log(): void
    {
        $departmentA = $this->department('DEPT-A');
        $departmentB = $this->department('DEPT-B');
        $actor = $this->userWithPermissions(
            $departmentA,
            ['worklogs.view']
        );
        $ticket = Ticket::query()->create([
            'ticket_no' => 'REQ-202608-7001',
            'handler_department_id' => $departmentA->id,
            'subject' => 'Collaborative isolation',
            'workflow_type' => 'collaborative',
        ]);
        $taskA = WorkTask::query()->create([
            'task_no' => 'TSK-202608-7001',
            'ticket_id' => $ticket->id,
            'department_id' => $departmentA->id,
            'title' => 'Department A task',
        ]);
        $taskB = WorkTask::query()->create([
            'task_no' => 'TSK-202608-7002',
            'ticket_id' => $ticket->id,
            'department_id' => $departmentB->id,
            'title' => 'Department B task',
        ]);

        TicketAssignment::query()->create([
            'ticket_id' => $ticket->id,
            'department_id' => $departmentA->id,
            'work_task_id' => $taskA->id,
        ]);
        TicketAssignment::query()->create([
            'ticket_id' => $ticket->id,
            'department_id' => $departmentB->id,
            'work_task_id' => $taskB->id,
            'sort_order' => 1,
        ]);

        $this->actingAs($actor);

        $visibleTaskIds = WorkTaskResource::getEloquentQuery()
            ->pluck('work_tasks.id')
            ->all();

        $this->assertContains($taskA->id, $visibleTaskIds);
        $this->assertNotContains($taskB->id, $visibleTaskIds);
        $this->assertFalse(WorkTaskResource::canView($taskB));
    }

    public function test_manual_work_log_rejects_department_outside_actor_scope(): void
    {
        $departmentA = $this->department('DEPT-A');
        $departmentB = $this->department('DEPT-B');
        $actor = $this->userWithPermissions(
            $departmentA,
            ['worklogs.manage']
        );
        $this->actingAs($actor);

        $this->expectException(ValidationException::class);

        app(WorkTaskMutationGuard::class)->validate($actor, [
            'department_id' => $departmentB->id,
            'title' => 'Unauthorized task',
        ]);
    }

    public function test_work_log_manager_can_render_the_scoped_create_form(): void
    {
        $department = $this->department('DEPT-A');
        $actor = $this->userWithPermissions(
            $department,
            ['worklogs.manage', 'tickets.manage']
        );

        $this->actingAs($actor)
            ->get('/panel/work-tasks/create')
            ->assertOk();
    }

    public function test_manual_work_log_rejects_pic_from_another_department(): void
    {
        $departmentA = $this->department('DEPT-A');
        $departmentB = $this->department('DEPT-B');
        $actor = $this->userWithPermissions(
            $departmentA,
            ['worklogs.manage']
        );
        $foreignPic = Employee::query()->create([
            'department_id' => $departmentB->id,
            'employee_no' => 'PIC-B',
            'name' => 'Foreign PIC',
            'is_active' => true,
        ]);
        $this->actingAs($actor);

        $this->expectException(ValidationException::class);

        app(WorkTaskMutationGuard::class)->validate($actor, [
            'department_id' => $departmentA->id,
            'employee_id' => $foreignPic->id,
            'title' => 'Invalid PIC task',
        ]);
    }

    public function test_manual_work_log_rejects_ticket_outside_actor_scope(): void
    {
        $departmentA = $this->department('DEPT-A');
        $departmentB = $this->department('DEPT-B');
        $actor = $this->userWithPermissions(
            $departmentA,
            ['worklogs.manage', 'tickets.manage']
        );
        $ticket = Ticket::query()->create([
            'ticket_no' => 'REQ-202608-7002',
            'handler_department_id' => $departmentB->id,
            'subject' => 'Restricted ticket',
            'workflow_type' => 'single',
        ]);
        $this->actingAs($actor);

        $this->expectException(ValidationException::class);

        app(WorkTaskMutationGuard::class)->validate($actor, [
            'ticket_id' => $ticket->id,
            'department_id' => $departmentA->id,
            'title' => 'Unauthorized linked task',
        ]);
    }

    private function department(string $code): Department
    {
        return Department::query()->create([
            'code' => $code,
            'name' => $code,
            'is_active' => true,
        ]);
    }

    private function userWithPermissions(
        Department $department,
        array $permissionCodes
    ): User {
        $user = User::factory()->create(['is_admin' => false]);
        Employee::query()->create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'employee_no' => 'EMP-'.$user->id,
            'name' => $user->name,
            'is_active' => true,
        ]);
        $role = Role::query()->create([
            'name' => 'Test Role '.$user->id,
            'code' => 'test-role-'.$user->id,
            'is_active' => true,
        ]);

        foreach ($permissionCodes as $permissionCode) {
            $permission = Permission::query()->create([
                'name' => $permissionCode,
                'code' => $permissionCode,
                'is_active' => true,
            ]);
            $role->permissions()->attach($permission);
        }

        $user->roles()->attach($role);

        return $user;
    }
}
