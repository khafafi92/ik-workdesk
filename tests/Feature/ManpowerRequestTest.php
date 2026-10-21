<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Pages\CreateTicket;
use App\Filament\Resources\Tickets\Pages\EditTicket;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\WorkTask;
use App\Notifications\TicketCreatedNotification;
use Database\Seeders\ManpowerRequestCategorySeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ManpowerRequestTest extends TestCase
{
    use RefreshDatabase;

    private Department $hr;

    private Department $it;

    private TicketCategory $category;

    private User $requester;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertEmpty(config('database.connections.sqlite.url'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->hr = Department::query()->create(['code' => 'HR', 'name' => 'Human Resources']);
        $this->it = Department::query()->create(['code' => 'IT', 'name' => 'Information Technology']);
        $this->category = TicketCategory::query()->create(['handler_department_id' => $this->hr->id,
            'name' => 'Kebutuhan Manpower', 'code' => 'HR-MP', 'is_active' => true, 'requires_manpower' => true]);
        $this->requester = User::factory()->create(['is_admin' => true]);
        Employee::query()->create(['user_id' => $this->requester->id, 'department_id' => $this->it->id,
            'name' => 'Requester pengujian', 'is_active' => true]);
        $this->actingAs($this->requester);
    }

    private function details(): array
    {
        return ['purpose' => 'Menambah dukungan operasional plant.', 'work_location' => 'Lokasi pengujian',
            'needed_at' => '2026-11-01', 'notes' => null, 'positions' => [
                ['title' => 'Operator', 'quantity' => 3, 'specifications' => 'Mengoperasikan peralatan produksi.', 'requirements' => 'Memahami operasi peralatan.'],
                ['title' => 'Teknisi', 'quantity' => 2, 'specifications' => 'Melakukan pemeliharaan peralatan.', 'requirements' => 'Berpengalaman dalam pemeliharaan.'],
            ]];
    }

    private function createRequest(array $overrides = []): Ticket
    {
        return Ticket::query()->create(['ticket_no' => 'R-MP-'.fake()->unique()->numerify('######'),
            'handler_department_id' => $this->hr->id, 'ticket_category_id' => $this->category->id,
            'employee_id' => $this->requester->employee->id, 'requester_department_id' => $this->it->id,
            'manpower_details' => $this->details(), ...$overrides]);
    }

    public function test_category_selection_switches_between_general_and_manpower_fields(): void
    {
        $normal = TicketCategory::query()->create(['handler_department_id' => $this->hr->id, 'name' => 'Layanan HR lainnya', 'is_active' => true]);

        Livewire::test(CreateTicket::class)
            ->fillForm(['handler_department_id' => $this->hr->id, 'ticket_category_id' => $this->category->id])
            ->assertFormFieldIsVisible('manpower_details.purpose')
            ->assertFormFieldIsHidden('subject')->assertFormFieldIsHidden('description')
            ->fillForm(['ticket_category_id' => $normal->id])
            ->assertFormFieldIsHidden('manpower_details.purpose')
            ->assertFormFieldIsVisible('subject')->assertFormFieldIsVisible('description');
    }

    public function test_livewire_creates_multiple_positions_and_complete_hr_work_log_without_mandatory_notes(): void
    {
        Livewire::test(CreateTicket::class)
            ->fillForm(['handler_department_id' => $this->hr->id, 'ticket_category_id' => $this->category->id,
                'manpower_details' => $this->details()])
            ->call('create')->assertHasNoFormErrors();

        $ticket = Ticket::query()->sole();
        $this->assertSame(2, count($ticket->manpower_details['positions']));
        $this->assertStringContainsString('5 orang', $ticket->subject);
        $this->assertNull($ticket->manpower_details['notes']);
        $this->assertSame($this->requester->employee->id, $ticket->employee_id);
        $task = WorkTask::query()->where('ticket_id', $ticket->id)->sole();
        $this->assertSame($this->hr->id, $task->department_id);
        $this->assertSame($ticket->description, $task->description);
        $this->assertStringContainsString('Operator: 3 orang', $task->description);
        $this->assertStringContainsString('Persyaratan kandidat: Berpengalaman dalam pemeliharaan.', $task->description);
        $this->assertSame(1, $ticket->assignments()->count());
    }

    public function test_invalid_headcounts_are_rejected_without_partial_ticket_or_work_log(): void
    {
        foreach ([0, -1, 1.5] as $quantity) {
            $details = $this->details();
            $details['positions'][0]['quantity'] = $quantity;
            Livewire::test(CreateTicket::class)
                ->fillForm(['handler_department_id' => $this->hr->id, 'ticket_category_id' => $this->category->id, 'manpower_details' => $details])
                ->call('create')->assertHasFormErrors();
        }
        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('work_tasks', 0);
    }

    public function test_server_requires_details_even_when_the_form_is_bypassed(): void
    {
        try {
            $this->createRequest(['manpower_details' => null]);
            $this->fail('Manpower details must be validated on the server.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('manpower_details.purpose', $exception->errors());
        }
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_forged_totals_and_unknown_fields_are_discarded(): void
    {
        $details = [...$this->details(), 'total' => 9999, 'approved_by' => $this->requester->id];
        $details['positions'][0]['approved_salary'] = 9999;
        $ticket = $this->createRequest(['subject' => 'Forged subject', 'description' => 'Forged description', 'manpower_details' => $details]);

        $this->assertArrayNotHasKey('total', $ticket->manpower_details);
        $this->assertArrayNotHasKey('approved_by', $ticket->manpower_details);
        $this->assertArrayNotHasKey('approved_salary', $ticket->manpower_details['positions'][0]);
        $this->assertStringContainsString('5 orang', $ticket->subject);
        $this->assertStringNotContainsString('Forged', $ticket->description);
    }

    public function test_manpower_category_cannot_be_forged_for_a_different_destination(): void
    {
        $this->expectException(ValidationException::class);
        $this->createRequest(['handler_department_id' => $this->it->id]);
    }

    public function test_details_round_trip_through_read_only_view_and_edit_even_after_category_is_disabled(): void
    {
        $ticket = $this->createRequest();
        $this->category->update(['requires_manpower' => false, 'is_active' => false]);
        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertFormFieldIsVisible('manpower_details.purpose')->assertFormFieldIsDisabled('manpower_details.purpose');
        $details = $this->details();
        $details['positions'][1]['quantity'] = 4;
        Livewire::test(EditTicket::class, ['record' => $ticket->id])
            ->fillForm(['manpower_details' => $details])->call('save')->assertHasNoFormErrors();

        $this->assertSame(4, $ticket->fresh()->manpower_details['positions'][1]['quantity']);
        $this->assertStringContainsString('7 orang', $ticket->fresh()->subject);
    }

    public function test_switching_to_a_normal_category_drops_hidden_manpower_payload(): void
    {
        $normal = TicketCategory::query()->create(['handler_department_id' => $this->hr->id, 'name' => 'Layanan HR lainnya']);
        $ticket = $this->createRequest();
        $ticket->update(['ticket_category_id' => $normal->id, 'subject' => 'Request biasa', 'description' => 'Kebutuhan biasa']);

        $this->assertNull($ticket->fresh()->manpower_details);
        $this->assertSame('Request biasa', $ticket->fresh()->subject);
    }

    public function test_enabling_manpower_for_a_category_preserves_legacy_requests(): void
    {
        $this->category->update(['requires_manpower' => false]);
        $ticket = $this->createRequest(['subject' => 'Permintaan lama', 'description' => 'Uraian lama', 'manpower_details' => null]);
        $this->category->update(['requires_manpower' => true]);
        $ticket->update(['priority' => 'high']);

        $this->assertSame('Uraian lama', $ticket->fresh()->description);
        $this->assertNull($ticket->fresh()->manpower_details);
        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertFormFieldIsVisible('subject')->assertFormFieldIsHidden('manpower_details.purpose');
    }

    public function test_seeder_is_idempotent_and_does_not_modify_non_hr_categories(): void
    {
        $other = TicketCategory::query()->create(['handler_department_id' => $this->it->id, 'name' => 'Manpower Plant']);
        $this->seed(ManpowerRequestCategorySeeder::class);
        $this->seed(ManpowerRequestCategorySeeder::class);

        $this->assertSame(1, TicketCategory::query()->where('handler_department_id', $this->hr->id)->where('requires_manpower', true)->count());
        $this->assertFalse($other->fresh()->requires_manpower);
    }

    public function test_old_category_names_become_one_choice_without_deleting_request_history(): void
    {
        $this->category->update(['name' => 'Kebutuhan Man Power']);
        $plant = TicketCategory::query()->create(['handler_department_id' => $this->hr->id,
            'name' => 'Manpower Plant', 'code' => 'HR-MP-PLANT', 'is_active' => true, 'requires_manpower' => true]);
        $ticket = $this->createRequest(['ticket_category_id' => $plant->id]);
        $before = $ticket->fresh()->getAttributes();

        $this->seed(ManpowerRequestCategorySeeder::class);
        $this->seed(ManpowerRequestCategorySeeder::class);

        $this->assertSame(['Kebutuhan Manpower'], TicketCategory::query()->where('handler_department_id', $this->hr->id)
            ->where('is_active', true)->pluck('name')->all());
        $this->assertFalse($plant->fresh()->is_active);
        $this->assertSame($before, $ticket->fresh()->getAttributes());
        Livewire::test(ViewTicket::class, ['record' => $ticket->id])->assertFormFieldIsVisible('manpower_details.purpose');
        $this->assertDatabaseHas('ticket_categories', ['id' => $plant->id]);
    }

    public function test_structured_manpower_details_do_not_expand_ticket_visibility(): void
    {
        $ticket = $this->createRequest();
        $otherDepartment = Department::query()->create(['code' => 'OTHER', 'name' => 'Other department']);
        $outsider = User::factory()->create(['is_admin' => false]);
        Employee::query()->create(['user_id' => $outsider->id, 'department_id' => $otherDepartment->id,
            'name' => 'Pengguna pengujian lain', 'is_active' => true]);
        $permission = Permission::query()->firstOrCreate(['code' => 'tickets.view'], ['name' => 'View Tickets', 'is_active' => true]);
        $outsider->directPermissions()->attach($permission);
        $this->actingAs($outsider);

        $this->assertFalse(TicketResource::canView($ticket));
        $this->assertFalse(TicketResource::getEloquentQuery()->whereKey($ticket->id)->exists());
        $this->get(TicketResource::getUrl('view', ['record' => $ticket->id]))->assertNotFound();
    }

    public function test_request_notifies_only_active_authorized_users_in_the_destination_department(): void
    {
        $permission = Permission::query()->firstOrCreate(['code' => 'tickets.view'], ['name' => 'View Tickets', 'is_active' => true]);
        $role = Role::query()->create(['name' => 'HR viewer', 'code' => 'hr-viewer', 'is_active' => true]);
        $role->permissions()->attach($permission);
        $users = [];
        foreach (['direct', 'role', 'no_email', 'no_access', 'inactive', 'other_department'] as $key) {
            $user = User::factory()->create(['is_admin' => false, ...($key === 'no_email' ? ['email' => ''] : [])]);
            Employee::query()->create(['user_id' => $user->id, 'department_id' => $key === 'other_department' ? $this->it->id : $this->hr->id,
                'name' => 'Penerima pengujian '.$key, 'is_active' => $key !== 'inactive']);
            if ($key === 'role') {
                $user->roles()->attach($role);
            } elseif ($key !== 'no_access') {
                $user->directPermissions()->attach($permission);
            }
            $users[$key] = $user;
        }
        $ticket = $this->createRequest();

        foreach (['direct', 'role'] as $key) {
            Notification::assertSentTo($users[$key], TicketCreatedNotification::class,
                fn (TicketCreatedNotification $notification, array $channels): bool => $channels === ['database'] && $notification->ticket->is($ticket));
            Notification::assertSentTo($users[$key], TicketCreatedNotification::class,
                fn (TicketCreatedNotification $notification, array $channels): bool => $channels === ['mail'] && $notification->afterCommit === true);
        }
        Notification::assertSentToTimes($users['no_email'], TicketCreatedNotification::class, 1);
        Notification::assertSentTo($users['no_email'], TicketCreatedNotification::class,
            fn (TicketCreatedNotification $notification, array $channels): bool => $channels === ['database']);
        foreach (['no_access', 'inactive', 'other_department'] as $key) {
            Notification::assertNotSentTo($users[$key], TicketCreatedNotification::class);
        }
        Notification::assertNotSentTo($this->requester, TicketCreatedNotification::class);
    }

    public function test_manpower_notification_persists_in_the_bell_and_uses_the_selected_mailer(): void
    {
        $ticket = $this->createRequest();
        $recipient = User::factory()->create(['name' => 'Penerima pengujian', 'email' => 'recipient@example.test']);
        $notification = new TicketCreatedNotification($ticket, 'array', ['address' => 'sender@example.test', 'name' => 'Pengujian']);

        (new ChannelManager(app()))->sendNow($recipient, $notification, ['database']);
        $data = $recipient->notifications()->sole()->data;
        $this->assertSame('filament', $data['format']);
        $this->assertStringContainsString('5 orang', $data['body']);
        $this->assertSame(TicketResource::getUrl('view', ['record' => $ticket->id]), $data['actions'][0]['url']);
        $mail = $notification->toMail($recipient);
        $this->assertSame('array', $mail->mailer);
        $this->assertStringContainsString('5 orang', implode(' ', $mail->introLines));
        $this->assertSame($data['actions'][0]['url'], $mail->actionUrl);
        $this->assertTrue($notification->afterCommit);
        $this->assertSame('service-desk-notifications', $notification->queue);
    }
}
