<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Reminder;
use App\Models\User;
use App\Models\WorkTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderTaskSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_reminder_form_shows_the_optional_task_source(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/panel/reminders/create')
            ->assertOk()
            ->assertSee('Source Task (Optional)');
    }

    public function test_a_reminder_can_optionally_reference_a_task(): void
    {
        $department = Department::query()->create([
            'code' => 'OPS',
            'name' => 'Operations',
            'is_active' => true,
        ]);

        $employee = Employee::query()->create([
            'department_id' => $department->id,
            'name' => 'Staff Test',
            'email' => 'staff@example.com',
            'is_active' => true,
        ]);

        $task = WorkTask::query()->create([
            'task_no' => 'TSK-202608-0001',
            'department_id' => $department->id,
            'employee_id' => $employee->id,
            'title' => 'Periksa laporan operasional',
            'description' => 'Pastikan laporan sudah lengkap.',
            'due_at' => now()->addDays(3),
        ]);

        $reminder = Reminder::query()->create([
            'employee_id' => $employee->id,
            'department_id' => $department->id,
            'work_task_id' => $task->id,
            'reminder_type' => 'task',
            'title' => '[TSK-202608-0001] Periksa laporan operasional',
            'reminder_at' => $task->due_at,
            'status' => 'pending',
        ]);

        $manualReminder = Reminder::query()->create([
            'employee_id' => $employee->id,
            'department_id' => $department->id,
            'reminder_type' => 'general',
            'title' => 'Reminder manual',
            'reminder_at' => now()->addDay(),
            'status' => 'pending',
        ]);

        $this->assertTrue($reminder->workTask->is($task));
        $this->assertTrue($task->reminders()->whereKey($reminder->id)->exists());
        $this->assertNull($manualReminder->work_task_id);

        $task->delete();

        $this->assertNull($reminder->fresh()->work_task_id);
    }

    public function test_a_reminder_can_be_marked_as_done_without_editing_the_form(): void
    {
        $reminder = Reminder::query()->create([
            'reminder_type' => 'general',
            'title' => 'Selesaikan reminder',
            'reminder_at' => now()->addDay(),
            'status' => 'pending',
        ]);

        $reminder->markAsDone();

        $this->assertSame('done', $reminder->fresh()->status);
    }
}
