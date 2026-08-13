<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Pages\CreateTicket;
use App\Models\Department;
use App\Models\PermitCompany;
use App\Models\PermitKbli;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\WorkTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PermitWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_permit_master_contains_kpmog_and_apca(): void
    {
        $this->assertDatabaseHas('permit_companies', ['code' => 'KPMOG']);
        $this->assertDatabaseHas('permit_companies', ['code' => 'APCA']);
    }

    public function test_duplicate_kbli_code_in_the_same_company_returns_validation_error(): void
    {
        $kpmog = PermitCompany::query()->where('code', 'KPMOG')->firstOrFail();

        PermitKbli::query()->create([
            'permit_company_id' => $kpmog->id,
            'code' => '42915',
            'name' => 'Existing KBLI',
            'is_active' => true,
        ]);

        try {
            PermitKbli::query()->create([
                'permit_company_id' => $kpmog->id,
                'code' => ' 42915 ',
                'name' => ' Existing KBLI ',
                'is_active' => true,
            ]);

            $this->fail('Duplicate KBLI should not be saved.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Nomor dan nama KBLI yang sama sudah terdaftar pada Permit Company tersebut.',
                $exception->errors()['name'][0],
            );
        }

        $this->assertSame(1, PermitKbli::query()
            ->where('permit_company_id', $kpmog->id)
            ->where('code', '42915')
            ->count());
    }

    public function test_same_kbli_code_with_different_names_can_be_used_in_the_same_company(): void
    {
        $kpmog = PermitCompany::query()->where('code', 'KPMOG')->firstOrFail();

        foreach (['Konstruksi Minyak', 'Konstruksi Gas'] as $name) {
            PermitKbli::query()->create([
                'permit_company_id' => $kpmog->id,
                'code' => '42915',
                'name' => $name,
                'is_active' => true,
            ]);
        }

        $this->assertSame(2, PermitKbli::query()
            ->where('permit_company_id', $kpmog->id)
            ->where('code', '42915')
            ->count());
    }

    public function test_same_kbli_code_can_be_used_by_different_companies(): void
    {
        $kpmog = PermitCompany::query()->where('code', 'KPMOG')->firstOrFail();
        $apca = PermitCompany::query()->where('code', 'APCA')->firstOrFail();

        foreach ([$kpmog, $apca] as $company) {
            PermitKbli::query()->create([
                'permit_company_id' => $company->id,
                'code' => '42915',
                'name' => "{$company->code} KBLI",
                'is_active' => true,
            ]);
        }

        $this->assertSame(2, PermitKbli::query()->where('code', '42915')->count());
    }

    public function test_admin_can_open_permit_master_and_service_desk_form(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/panel/permit-companies')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/panel/service-desk/create')
            ->assertOk()
            ->assertDontSee('Permit Company');
    }

    public function test_kbli_can_be_selected_from_the_popup_picker(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $company = PermitCompany::query()->where('code', 'KPMOG')->firstOrFail();
        $category = $this->permitCategory();

        Livewire::actingAs($admin)
            ->test(CreateTicket::class)
            ->fillForm([
                'ticket_category_id' => $category->id,
                'permit_company_id' => $company->id,
            ])
            ->assertFormComponentActionExists('permit_kbli_id', 'select')
            ->assertFormComponentActionEnabled('permit_kbli_id', 'select')
            ->mountFormComponentAction('permit_kbli_id', 'select')
            ->assertFormComponentActionMounted('permit_kbli_id', 'select');
    }

    public function test_missing_kbli_sets_discussion_and_survives_work_log_creation(): void
    {
        $kpmog = PermitCompany::query()->where('code', 'KPMOG')->firstOrFail();
        $category = $this->permitCategory();
        $ticket = Ticket::query()->create([
            'ticket_no' => 'R PERMIT-0001',
            'ticket_category_id' => $category->id,
            'permit_company_id' => $kpmog->id,
            'permit_kbli_unavailable' => true,
            'subject' => 'Permit without registered KBLI',
            'status' => 'open',
        ]);

        $this->assertSame('discussion', $ticket->status);
        $this->assertNull($ticket->permit_kbli_id);

        WorkTask::query()->create([
            'task_no' => 'T PERMIT-0001',
            'ticket_id' => $ticket->id,
            'title' => 'Discuss missing KBLI',
            'status' => 'planned',
        ]);

        $this->assertSame('discussion', $ticket->fresh()->status);
    }

    public function test_kbli_must_belong_to_selected_company(): void
    {
        $kpmog = PermitCompany::query()->where('code', 'KPMOG')->firstOrFail();
        $apca = PermitCompany::query()->where('code', 'APCA')->firstOrFail();
        $category = $this->permitCategory();
        $apcaKbli = PermitKbli::query()->create([
            'permit_company_id' => $apca->id,
            'code' => 'TEST-APCA',
            'name' => 'APCA Test KBLI',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        Ticket::query()->create([
            'ticket_no' => 'R PERMIT-0002',
            'ticket_category_id' => $category->id,
            'permit_company_id' => $kpmog->id,
            'permit_kbli_id' => $apcaKbli->id,
            'subject' => 'Invalid cross-company KBLI',
        ]);
    }

    public function test_selecting_registered_kbli_returns_discussion_to_open(): void
    {
        $kpmog = PermitCompany::query()->where('code', 'KPMOG')->firstOrFail();
        $category = $this->permitCategory();
        $kbli = PermitKbli::query()->create([
            'permit_company_id' => $kpmog->id,
            'code' => 'TEST-KPMOG',
            'name' => 'KPMOG Test KBLI',
            'is_active' => true,
        ]);
        $ticket = Ticket::query()->create([
            'ticket_no' => 'R PERMIT-0003',
            'ticket_category_id' => $category->id,
            'permit_company_id' => $kpmog->id,
            'permit_kbli_unavailable' => true,
            'subject' => 'Resolve KBLI discussion',
        ]);

        $ticket->update([
            'permit_kbli_unavailable' => false,
            'permit_kbli_id' => $kbli->id,
        ]);

        $this->assertSame('open', $ticket->fresh()->status);
        $this->assertSame($kbli->id, $ticket->fresh()->permit_kbli_id);
    }

    public function test_selected_kbli_and_legal_result_are_available_on_the_legal_task(): void
    {
        Storage::fake('local');

        $legal = Department::query()->firstOrCreate(
            ['code' => 'LG'],
            ['name' => 'Legal', 'is_active' => true]
        );
        $company = PermitCompany::query()->where('code', 'KPMOG')->firstOrFail();
        $category = $this->permitCategory();
        $kbli = PermitKbli::query()->create([
            'permit_company_id' => $company->id,
            'code' => '42915',
            'name' => 'Pengerukan',
            'is_active' => true,
        ]);
        $ticket = Ticket::query()->create([
            'ticket_no' => 'R-PERMIT-RESULT-0001',
            'handler_department_id' => $legal->id,
            'ticket_category_id' => $category->id,
            'permit_company_id' => $company->id,
            'permit_kbli_id' => $kbli->id,
            'subject' => 'Permintaan KBLI Pengerukan',
        ]);
        $task = WorkTask::query()->create([
            'task_no' => 'T-PERMIT-RESULT-0001',
            'ticket_id' => $ticket->id,
            'department_id' => $legal->id,
            'title' => 'Proses Permit KBLI',
            'status' => 'planned',
        ]);

        $task->update([
            'permit_result_notes' => 'KBLI telah selesai diproses.',
            'permit_result_attachments' => ['permit-results/kbli-42915.pdf'],
        ]);
        $task->refresh()->load(['ticket.permitCompany', 'ticket.permitKbli']);

        $this->assertTrue($task->isPermitLegalTask());
        $this->assertSame('KPMOG', $task->ticket->permitCompany->code);
        $this->assertSame('42915', $task->ticket->permitKbli->code);
        $this->assertSame('Pengerukan', $task->ticket->permitKbli->name);
        $this->assertSame(
            ['permit-results/kbli-42915.pdf'],
            $task->permit_result_attachments,
        );

        Storage::disk('local')->put(
            'permit-results/kbli-42915.pdf',
            'permit-result'
        );
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('work-tasks.permit-results.download', [$task, 0]))
            ->assertOk();

        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('work-tasks.permit-results.download', [$task, 0]))
            ->assertForbidden();
    }

    public function test_permit_result_cannot_be_added_to_a_non_permit_task(): void
    {
        $department = Department::query()->firstOrCreate(
            ['code' => 'IT'],
            ['name' => 'Information Technology', 'is_active' => true]
        );
        $task = WorkTask::query()->create([
            'task_no' => 'T-NON-PERMIT-0001',
            'department_id' => $department->id,
            'title' => 'Regular Task',
            'status' => 'planned',
        ]);

        $this->expectException(ValidationException::class);

        $task->update(['permit_result_notes' => 'Tidak semestinya dapat diisi.']);
    }

    public function test_non_permit_category_does_not_keep_permit_values(): void
    {
        $company = PermitCompany::query()->where('code', 'KPMOG')->firstOrFail();
        $category = TicketCategory::query()->create([
            'name' => 'Regular Request',
            'code' => 'REGULAR',
            'workflow_type' => 'single',
            'requires_permit' => false,
            'is_active' => true,
        ]);

        $ticket = Ticket::query()->create([
            'ticket_no' => 'R REGULAR-0001',
            'ticket_category_id' => $category->id,
            'permit_company_id' => $company->id,
            'permit_kbli_unavailable' => true,
            'subject' => 'Regular request without permit trigger',
        ]);

        $ticket->refresh();

        $this->assertNull($ticket->permit_company_id);
        $this->assertFalse($ticket->permit_kbli_unavailable);
        $this->assertSame('open', $ticket->status);
    }

    private function permitCategory(): TicketCategory
    {
        $legal = Department::query()->firstOrCreate(
            ['code' => 'LG'],
            ['name' => 'Legal', 'is_active' => true]
        );

        return TicketCategory::query()->create([
            'handler_department_id' => $legal->id,
            'name' => 'Kebutuhan Tender',
            'code' => 'LG-TENDER-TEST',
            'workflow_type' => 'single',
            'requires_permit' => true,
            'is_active' => true,
        ]);
    }
}
