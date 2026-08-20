<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\TicketResource;
use App\Livewire\TicketCollaborationRoom;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PendingLegalCollaborationLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_can_view_but_cannot_comment_until_cbo_approval(): void
    {
        $legal = Department::query()->create([
            'code' => 'LG',
            'name' => 'Legal',
            'is_active' => true,
        ]);
        $legalUser = User::factory()->create();
        Employee::query()->create([
            'user_id' => $legalUser->id,
            'department_id' => $legal->id,
            'employee_no' => 'LEGAL-COMMENT-001',
            'name' => 'Legal Commenter',
            'is_active' => true,
        ]);
        $legalRole = Role::query()->create([
            'name' => 'Legal Collaborator',
            'code' => 'legal-collaborator',
            'is_active' => true,
        ]);
        $permissionIds = collect([
            ['name' => 'View Tickets', 'code' => 'tickets.view'],
            ['name' => 'Manage Tickets', 'code' => 'tickets.manage'],
            ['name' => 'Create Comments', 'code' => 'comments.create'],
            ['name' => 'View Work Logs', 'code' => 'worklogs.view'],
        ])->map(fn (array $permission): int => Permission::query()->updateOrCreate(
            ['code' => $permission['code']],
            [...$permission, 'is_active' => true]
        )->id);
        $legalRole->permissions()->sync($permissionIds);
        $legalUser->roles()->attach($legalRole);

        $ticket = Ticket::query()->create([
            'ticket_no' => 'REQ-LEGAL-COMMENT-001',
            'handler_department_id' => $legal->id,
            'subject' => 'Pending legal request',
        ]);
        $task = WorkTask::query()->create([
            'task_no' => 'TSK-LEGAL-COMMENT-001',
            'ticket_id' => $ticket->id,
            'department_id' => $legal->id,
            'title' => 'Pending legal work',
            'status' => 'planned',
        ]);

        Livewire::actingAs($legalUser)
            ->test(TicketCollaborationRoom::class, ['record' => $ticket])
            ->assertSee('Menunggu approval CBO')
            ->set('message', 'Komentar yang seharusnya ditolak')
            ->call('addMessage')
            ->assertForbidden();

        $this->assertFalse(TicketResource::canEdit($ticket));

        $this->assertFalse(
            $ticket->comments()
                ->where('activity_type', 'message')
                ->exists()
        );

        $admin = User::factory()->create(['is_admin' => true]);
        $task->approveLegalTask($admin);

        $this->assertTrue(TicketResource::canEdit($ticket));

        Livewire::actingAs($legalUser)
            ->test(TicketCollaborationRoom::class, ['record' => $ticket])
            ->assertDontSee('Menunggu approval CBO')
            ->assertSee('Menunggu penugasan PIC')
            ->set('message', 'Task sudah approved')
            ->call('addMessage')
            ->assertForbidden();

        $task->update(['employee_id' => $legalUser->employee->id]);

        Livewire::actingAs($legalUser)
            ->test(TicketCollaborationRoom::class, ['record' => $ticket])
            ->assertDontSee('Menunggu penugasan PIC')
            ->set('message', 'Task sudah approved dan PIC sudah ditugaskan')
            ->call('addMessage')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'user_id' => $legalUser->id,
            'activity_type' => 'message',
            'message' => 'Task sudah approved dan PIC sudah ditugaskan',
        ]);
    }
}
