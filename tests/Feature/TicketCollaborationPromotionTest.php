<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\User;
use App\Models\WorkTask;
use App\Services\TicketCollaborationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketCollaborationPromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_progress_single_department_ticket_can_become_collaborative_without_resetting_its_primary_work_log(): void
    {
        $requester = Department::query()->create([
            'code' => 'REQ',
            'name' => 'Requester Department',
            'is_active' => true,
        ]);
        $handler = Department::query()->create([
            'code' => 'IT',
            'name' => 'Information Technology',
            'is_active' => true,
        ]);
        $collaborator = Department::query()->create([
            'code' => 'GA',
            'name' => 'General Affairs',
            'is_active' => true,
        ]);
        $ticket = Ticket::query()->create([
            'ticket_no' => 'R-COLLAB-0001',
            'requester_department_id' => $requester->id,
            'handler_department_id' => $handler->id,
            'subject' => 'Relokasi perangkat kerja',
            'description' => 'Membutuhkan dukungan IT dan GA.',
            'priority' => 'high',
            'status' => 'in_progress',
            'workflow_type' => 'single',
            'reported_at' => now(),
        ]);
        $primaryTask = WorkTask::query()->create([
            'task_no' => 'T-COLLAB-0001',
            'ticket_id' => $ticket->id,
            'department_id' => $handler->id,
            'title' => $ticket->subject,
            'description' => $ticket->description,
            'priority' => $ticket->priority,
            'status' => 'in_progress',
            'progress_percent' => 40,
        ]);
        TicketAssignment::query()->create([
            'ticket_id' => $ticket->id,
            'department_id' => $handler->id,
            'work_task_id' => $primaryTask->id,
            'is_required' => true,
            'sort_order' => 1,
            'notes' => 'Lead department',
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test(ViewTicket::class, ['record' => $ticket->id])
            ->assertActionVisible('promoteToCollaborative')
            ->callAction('promoteToCollaborative', [
                'department_ids' => [$collaborator->id],
            ]);

        $promoted = $ticket->fresh();

        $this->assertSame('collaborative', $promoted->workflow_type);
        $this->assertSame('in_progress', $promoted->status);
        $this->assertSame('in_progress', $primaryTask->fresh()->status);
        $this->assertSame(40, $primaryTask->fresh()->progress_percent);
        $this->assertDatabaseHas('ticket_assignments', [
            'ticket_id' => $ticket->id,
            'department_id' => $collaborator->id,
            'is_required' => true,
        ]);
        $this->assertDatabaseHas('work_tasks', [
            'ticket_id' => $ticket->id,
            'department_id' => $collaborator->id,
            'status' => 'planned',
            'work_scope' => 'service_request',
        ]);
    }

    public function test_collaboration_options_exclude_the_requester_and_primary_department(): void
    {
        $requester = Department::query()->create(['code' => 'REQ', 'name' => 'Requester', 'is_active' => true]);
        $handler = Department::query()->create(['code' => 'IT', 'name' => 'Information Technology', 'is_active' => true]);
        $collaborator = Department::query()->create(['code' => 'GA', 'name' => 'General Affairs', 'is_active' => true]);
        $ticket = Ticket::query()->create([
            'ticket_no' => 'R-COLLAB-0002',
            'requester_department_id' => $requester->id,
            'handler_department_id' => $handler->id,
            'subject' => 'Koordinasi lintas departemen',
            'workflow_type' => 'single',
            'reported_at' => now(),
        ]);

        $options = app(TicketCollaborationService::class)->additionalDepartmentOptions($ticket);

        $this->assertSame([''.$collaborator->id => 'General Affairs'], $options);
    }
}
