<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Pages\CreateTicket;
use App\Filament\Resources\Tickets\Pages\EditTicket;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\WorkTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LegalRequestDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_request_details_and_document_types_are_stored(): void
    {
        $legal = Department::query()->create([
            'code' => 'LEGAL',
            'name' => 'Legal',
            'is_active' => true,
        ]);

        $ticket = Ticket::query()->create([
            'ticket_no' => 'R-LEGAL-FORM-0001',
            'handler_department_id' => $legal->id,
            'subject' => 'Review perjanjian',
            'description' => 'Proyek pengadaan armada',
            'legal_background' => 'Perusahaan membutuhkan armada baru.',
            'legal_objective' => 'Memperoleh perjanjian yang melindungi perusahaan.',
            'legal_desired_scheme' => 'Kerja sama pengadaan selama tiga tahun.',
            'legal_document_types' => ['draft_agreement', 'proposal'],
        ]);

        $this->assertSame(
            ['draft_agreement', 'proposal'],
            $ticket->fresh()->legal_document_types
        );
        $this->assertSame(
            'Perusahaan membutuhkan armada baru.',
            $ticket->fresh()->legal_background
        );
    }

    public function test_legal_request_form_requires_the_new_details(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $legal = Department::query()->create([
            'code' => 'LEGAL-FORM',
            'name' => 'Legal',
            'is_active' => true,
        ]);
        $category = TicketCategory::query()->create([
            'handler_department_id' => $legal->id,
            'name' => 'Review Perjanjian',
            'code' => 'LEGAL-REVIEW',
            'workflow_type' => 'single',
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(CreateTicket::class)
            ->fillForm([
                'handler_department_id' => $legal->id,
                'ticket_category_id' => $category->id,
                'subject' => 'Review draft kerja sama',
                'description' => null,
                'legal_background' => null,
                'legal_objective' => null,
                'legal_desired_scheme' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'description' => 'required',
                'legal_background' => 'required',
                'legal_objective' => 'required',
                'legal_desired_scheme' => 'required',
            ]);
    }

    public function test_only_requester_can_access_rejected_legal_revision_flow(): void
    {
        $legal = Department::query()->create([
            'code' => 'LEGAL-REVISION',
            'name' => 'Legal',
            'is_active' => true,
        ]);
        $requester = User::factory()->create();
        $employee = Employee::query()->create([
            'user_id' => $requester->id,
            'department_id' => $legal->id,
            'name' => 'Requester',
            'is_active' => true,
        ]);
        $otherUser = User::factory()->create();
        $ticket = Ticket::query()->create([
            'ticket_no' => 'R-LEGAL-FORM-0002',
            'employee_id' => $employee->id,
            'handler_department_id' => $legal->id,
            'subject' => 'Rejected request',
            'status' => 'rejected',
        ]);
        WorkTask::query()->create([
            'task_no' => 'T-LEGAL-FORM-0002',
            'ticket_id' => $ticket->id,
            'department_id' => $legal->id,
            'title' => 'Rejected task',
            'approval_status' => 'rejected',
        ])->updateQuietly(['approval_status' => 'rejected']);

        $this->assertTrue(
            TicketResource::canReviseRejectedLegalRequest($ticket, $requester)
        );
        $this->assertFalse(
            TicketResource::canReviseRejectedLegalRequest($ticket, $otherUser)
        );
    }

    public function test_requester_edit_submits_rejected_legal_task_for_approval_again(): void
    {
        $requesterDepartment = Department::query()->create([
            'code' => 'FIN-REVISION',
            'name' => 'Finance',
            'is_active' => true,
        ]);
        $legal = Department::query()->create([
            'code' => 'LEGAL-EDIT',
            'name' => 'Legal',
            'is_active' => true,
        ]);
        $category = TicketCategory::query()->create([
            'handler_department_id' => $legal->id,
            'name' => 'Legal Opinion',
            'code' => 'LEGAL-OPINION',
            'workflow_type' => 'single',
            'is_active' => true,
        ]);
        $requester = User::factory()->create();
        $requester->roles()->attach(
            Role::query()->where('code', 'requester')->value('id')
        );
        $viewPermission = Permission::query()->firstOrCreate(
            ['code' => 'tickets.view'],
            [
                'name' => 'View Tickets',
                'module' => 'Service Desk',
                'is_active' => true,
            ]
        );
        $requester->directPermissions()->attach($viewPermission);
        $employee = Employee::query()->create([
            'user_id' => $requester->id,
            'department_id' => $requesterDepartment->id,
            'name' => 'Requester Revision',
            'is_active' => true,
        ]);
        $cbo = User::factory()->create();
        $cbo->roles()->attach(Role::query()->where('code', 'cbo')->value('id'));
        $ticket = Ticket::query()->create([
            'ticket_no' => 'R-LEGAL-EDIT-0001',
            'employee_id' => $employee->id,
            'requester_department_id' => $requesterDepartment->id,
            'handler_department_id' => $legal->id,
            'ticket_category_id' => $category->id,
            'subject' => 'Draft legal opinion',
            'description' => 'Project Alpha',
            'legal_background' => 'Background lama',
            'legal_objective' => 'Objective lama',
            'legal_desired_scheme' => 'Scheme lama',
        ]);
        $task = WorkTask::query()->create([
            'task_no' => 'T-LEGAL-EDIT-0001',
            'ticket_id' => $ticket->id,
            'department_id' => $legal->id,
            'title' => $ticket->subject,
            'description' => $ticket->description,
            'status' => 'planned',
        ]);

        $this->actingAs($cbo);
        $task->rejectLegalTask($cbo, 'Latar belakang belum rinci.');

        $requester = $requester->fresh();
        $this->actingAs($requester);
        $this->assertTrue($requester->hasPermission('tickets.view'));
        $this->assertSame($employee->id, $requester->employee?->id);
        $this->assertSame($employee->id, $ticket->fresh()->employee_id);
        $this->assertTrue(TicketResource::canView($ticket->fresh()));
        $this->assertTrue(
            TicketResource::getEloquentQuery()->whereKey($ticket->id)->exists()
        );

        Livewire::actingAs($requester)
            ->test(EditTicket::class, ['record' => $ticket->id])
            ->fillForm([
                'subject' => 'Revised legal opinion',
                'description' => 'Project Alpha',
                'legal_background' => 'Background sudah diperinci.',
                'legal_objective' => 'Mendapatkan legal opinion.',
                'legal_desired_scheme' => 'Review dan pemberian opini tertulis.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('open', $ticket->fresh()->status);
        $this->assertSame('pending', $task->fresh()->approval_status);
        $this->assertSame('Revised legal opinion', $task->fresh()->title);
        $this->assertSame(
            'Background sudah diperinci.',
            $ticket->fresh()->legal_background
        );
    }
}
