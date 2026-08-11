<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketAssignedPicTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_desk_query_loads_pic_from_its_work_log(): void
    {
        $department = Department::query()->create([
            'code' => 'IT',
            'name' => 'Information Technology',
            'is_active' => true,
        ]);
        $pic = Employee::query()->create([
            'department_id' => $department->id,
            'name' => 'Fajar',
            'is_active' => true,
        ]);
        $ticket = Ticket::query()->create([
            'ticket_no' => 'REQ-202608-9901',
            'handler_department_id' => $department->id,
            'subject' => 'Assigned task',
        ]);
        WorkTask::query()->create([
            'task_no' => 'TSK-202608-9901',
            'ticket_id' => $ticket->id,
            'department_id' => $department->id,
            'employee_id' => $pic->id,
            'title' => $ticket->subject,
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);

        $result = TicketResource::getEloquentQuery()->findOrFail($ticket->id);

        $this->assertTrue($result->relationLoaded('workTasks'));
        $this->assertSame(
            ['Fajar'],
            $result->workTasks->pluck('employee.name')->all()
        );
    }
}
