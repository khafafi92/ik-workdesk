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
use App\Services\WorkTaskMutationGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AssignedPicExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_pic_with_viewer_role_can_start_the_task(): void
    {
        [$user, $task] = $this->assignedPicContext();
        $this->actingAs($user);

        $this->assertTrue(WorkTaskResource::canEdit($task));

        $data = app(WorkTaskMutationGuard::class)->validate($user, [
            'status' => 'in_progress',
            'progress_percent' => 25,
            'notes' => 'Pekerjaan sedang dilakukan.',
        ], $task);
        $task->update($data);

        $this->assertSame('in_progress', $task->fresh()->status);
        $this->assertSame(25, (int) $task->fresh()->progress_percent);
        $this->assertNotNull($task->fresh()->start_at);
    }

    public function test_assigned_pic_cannot_reassign_or_complete_the_task(): void
    {
        [$user, $task] = $this->assignedPicContext();
        $this->actingAs($user);

        try {
            app(WorkTaskMutationGuard::class)->validate($user, [
                'employee_id' => null,
                'status' => 'done',
            ], $task);
            $this->fail('PIC should not be allowed to reassign or complete a task.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
    }

    public function test_assigned_pic_can_adjust_priority_and_due_date_with_activity_log(): void
    {
        [$user, $task] = $this->assignedPicContext();
        $this->actingAs($user);

        $data = app(WorkTaskMutationGuard::class)->validate($user, [
            'priority' => 'urgent',
            'due_at' => '2026-08-20 17:00:00',
        ], $task);
        $task->update($data);

        $this->assertSame('urgent', $task->fresh()->priority);
        $this->assertSame(
            '2026-08-20 17:00:00',
            $task->fresh()->due_at?->format('Y-m-d H:i:s')
        );
        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $task->ticket_id,
            'work_task_id' => $task->id,
            'user_id' => $user->id,
            'activity_type' => 'priority_change',
        ]);
        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $task->ticket_id,
            'work_task_id' => $task->id,
            'user_id' => $user->id,
            'activity_type' => 'due_date_change',
        ]);
    }

    private function assignedPicContext(): array
    {
        $department = Department::query()->create([
            'code' => 'IT',
            'name' => 'Information Technology',
            'is_active' => true,
        ]);
        $user = User::factory()->create(['is_admin' => false]);
        $employee = Employee::query()->create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'name' => 'Assigned User',
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
        $ticketPermission = Permission::query()->create([
            'name' => 'View Service Desk',
            'code' => 'tickets.view',
            'is_active' => true,
        ]);
        $role->permissions()->attach($ticketPermission);
        $user->roles()->attach($role);
        $ticket = Ticket::query()->create([
            'ticket_no' => 'R PIC-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
            'handler_department_id' => $department->id,
            'subject' => 'Assigned PIC task',
            'reported_at' => now(),
        ]);
        $task = WorkTask::query()->create([
            'task_no' => 'TSK-202608-8801',
            'ticket_id' => $ticket->id,
            'department_id' => $department->id,
            'employee_id' => $employee->id,
            'title' => 'Assigned task',
            'status' => 'planned',
        ]);

        return [$user, $task];
    }
}
