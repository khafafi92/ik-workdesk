<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkTask;
use App\Services\WorkTaskMutationGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkTaskClaimTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_department_user_cannot_claim_unassigned_collaborative_task(): void
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

        $this->assertFalse($task->canBeClaimedBy($user));
    }

    public function test_manager_and_supervisor_can_claim_non_legal_task(): void
    {
        $department = $this->department('SCM');
        $manager = $this->departmentLeader($department, 'department-manager');
        $supervisor = $this->departmentLeader($department, 'supervisor');

        foreach ([$manager, $supervisor] as $index => $user) {
            $task = WorkTask::query()->create([
                'task_no' => 'TSK-SCM-'.($index + 1),
                'department_id' => $department->id,
                'title' => 'SCM task',
                'status' => 'planned',
            ]);

            $this->assertTrue($task->canBeClaimedBy($user));
        }
    }

    public function test_only_manager_can_claim_or_assign_legal_task(): void
    {
        $legal = $this->department('LEGAL');
        $manager = $this->departmentLeader($legal, 'department-manager');
        $supervisor = $this->departmentLeader($legal, 'supervisor');
        $task = WorkTask::query()->create([
            'task_no' => 'TSK-LEGAL-CLAIM',
            'department_id' => $legal->id,
            'title' => 'Legal task',
            'status' => 'planned',
        ]);
        $task->updateQuietly(['approval_status' => 'approved']);

        $this->assertTrue($task->fresh()->canBeClaimedBy($manager));
        $this->assertFalse($task->fresh()->canBeClaimedBy($supervisor));
        $this->assertTrue($task->fresh()->canAssignPicBy($manager));
        $this->assertFalse($task->fresh()->canAssignPicBy($supervisor));
    }

    public function test_supervisor_can_assign_staff_outside_legal_but_not_inside_legal(): void
    {
        $scm = $this->department('SCM-ASSIGN');
        $legal = $this->department('LEGAL-ASSIGN');
        $scmSupervisor = $this->departmentLeader($scm, 'supervisor');
        $legalSupervisor = $this->departmentLeader($legal, 'supervisor');
        $scmStaff = Employee::query()->create([
            'department_id' => $scm->id,
            'name' => 'SCM Staff',
            'is_active' => true,
        ]);
        $legalStaff = Employee::query()->create([
            'department_id' => $legal->id,
            'name' => 'Legal Staff',
            'is_active' => true,
        ]);
        $scmTask = WorkTask::query()->create([
            'task_no' => 'TSK-SCM-ASSIGN',
            'department_id' => $scm->id,
            'title' => 'SCM assignment',
            'status' => 'planned',
        ]);
        $legalTask = WorkTask::query()->create([
            'task_no' => 'TSK-LEGAL-ASSIGN',
            'department_id' => $legal->id,
            'title' => 'Legal assignment',
            'status' => 'planned',
        ]);
        $legalTask->updateQuietly(['approval_status' => 'approved']);
        $guard = app(WorkTaskMutationGuard::class);

        $validated = $guard->validate($scmSupervisor, [
            'department_id' => $scm->id,
            'employee_id' => $scmStaff->id,
        ], $scmTask);
        $this->assertSame($scmStaff->id, $validated['employee_id']);

        try {
            $guard->validate($legalSupervisor, [
                'department_id' => $legal->id,
                'employee_id' => $legalStaff->id,
            ], $legalTask->fresh());
            $this->fail('Supervisor Legal tidak boleh menentukan PIC.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('employee_id', $exception->errors());
        }
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

    private function departmentLeader(Department $department, string $roleCode): User
    {
        $user = User::factory()->create(['is_admin' => false]);
        Employee::query()->create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'name' => $roleCode,
            'is_active' => true,
        ]);
        $permission = Permission::query()->firstOrCreate(
            ['code' => 'worklogs.manage'],
            ['name' => 'Manage Work Logs', 'is_active' => true]
        );
        $role = Role::query()->firstOrCreate(
            ['code' => $roleCode],
            ['name' => $roleCode, 'is_active' => true]
        );
        $role->permissions()->syncWithoutDetaching($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
