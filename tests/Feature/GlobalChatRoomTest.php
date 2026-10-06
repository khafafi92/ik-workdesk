<?php

namespace Tests\Feature;

use App\Filament\Pages\GlobalChatRoom;
use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Models\Employee;
use App\Models\GlobalChatAttachment;
use App\Models\GlobalChatMessage;
use App\Models\PermitCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class GlobalChatRoomTest extends TestCase
{
    use RefreshDatabase;

    private PermitCompany $alpha;

    private PermitCompany $beta;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('broadcasting.default', 'null');

        $this->alpha = PermitCompany::query()->create([
            'code' => 'ALPHA',
            'name' => 'Company Alpha',
            'is_active' => true,
        ]);

        $this->beta = PermitCompany::query()->create([
            'code' => 'BETA',
            'name' => 'Company Beta',
            'is_active' => true,
        ]);
    }

    public function test_employee_only_sees_and_selects_their_company_room(): void
    {
        $user = $this->employeeUser($this->alpha);

        $component = Livewire::actingAs($user)
            ->test(GlobalChatRoom::class)
            ->assertSee('Company Alpha')
            ->assertDontSee('Company Beta');

        $component->call('selectCompany', $this->beta->id)
            ->assertForbidden();
    }

    public function test_employee_can_be_assigned_multiple_companies_from_the_master_form(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(CreateEmployee::class)
            ->fillForm([
                'user_id' => $user->id,
                'name' => 'Cross-company CBO',
                'is_active' => true,
                'permitCompanies' => [$this->alpha->id, $this->beta->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $employee = Employee::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertEqualsCanonicalizing(
            [$this->alpha->id, $this->beta->id],
            $employee->permitCompanies()->pluck('permit_companies.id')->all(),
        );

        Livewire::actingAs($admin)
            ->test(ListEmployees::class)
            ->assertSee('Company Alpha')
            ->assertSee('Company Beta');

        Livewire::actingAs($user)
            ->test(GlobalChatRoom::class)
            ->assertSee('Company Alpha')
            ->assertSee('Company Beta')
            ->call('selectCompany', $this->beta->id)
            ->set('message', 'Pembaruan lintas company')
            ->call('addMessage')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('global_chat_messages', [
            'permit_company_id' => $this->beta->id,
            'user_id' => $user->id,
            'body' => 'Pembaruan lintas company',
        ]);

        Livewire::actingAs($admin)
            ->test(EditEmployee::class, ['record' => $employee->id])
            ->fillForm(['permitCompanies' => [$this->beta->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(
            [$this->beta->id],
            $employee->fresh()->permitCompanies()->pluck('permit_companies.id')->all(),
        );
    }

    public function test_users_without_an_employee_cannot_access_company_rooms(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(GlobalChatRoom::class)
            ->assertSee('belum ditetapkan ke company')
            ->assertDontSee('Company Alpha')
            ->assertDontSee('Company Beta')
            ->call('selectCompany', $this->beta->id)
            ->assertForbidden();
    }

    public function test_employee_without_a_company_cannot_access_company_rooms(): void
    {
        $user = User::factory()->create();

        Employee::query()->create([
            'user_id' => $user->id,
            'name' => $user->name,
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(GlobalChatRoom::class)
            ->assertSee('belum ditetapkan ke company')
            ->assertDontSee('Company Alpha')
            ->assertDontSee('Company Beta')
            ->call('selectCompany', $this->beta->id)
            ->assertForbidden();
    }

    public function test_company_employee_can_send_a_message_to_their_room(): void
    {
        $user = $this->employeeUser($this->alpha);

        Livewire::actingAs($user)
            ->test(GlobalChatRoom::class)
            ->set('message', 'Halo, Company Alpha')
            ->call('addMessage')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('global_chat_messages', [
            'permit_company_id' => $this->alpha->id,
            'user_id' => $user->id,
            'body' => 'Halo, Company Alpha',
        ]);
    }

    public function test_empty_message_and_attachment_are_rejected(): void
    {
        $user = $this->employeeUser($this->alpha);

        Livewire::actingAs($user)
            ->test(GlobalChatRoom::class)
            ->set('message', '  ')
            ->call('addMessage')
            ->assertHasErrors('message');

        $this->assertDatabaseCount('global_chat_messages', 0);
    }

    public function test_attachments_are_stored_privately_and_only_the_company_can_download_them(): void
    {
        Storage::fake('local');

        $alphaUser = $this->employeeUser($this->alpha);
        $betaUser = $this->employeeUser($this->beta);

        Livewire::actingAs($alphaUser)
            ->test(GlobalChatRoom::class)
            ->set('attachments', [
                UploadedFile::fake()->create('panduan.pdf', 100, 'application/pdf'),
            ])
            ->call('addMessage')
            ->assertHasNoErrors();

        $message = GlobalChatMessage::query()->where('user_id', $alphaUser->id)->firstOrFail();
        $attachment = GlobalChatAttachment::query()
            ->where('global_chat_message_id', $message->id)
            ->firstOrFail();

        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($betaUser)
            ->get(route('global-chat.attachments.download', [$message, $attachment]))
            ->assertNotFound();

        $this->actingAs($alphaUser)
            ->get(route('global-chat.attachments.download', [$message, $attachment]))
            ->assertOk();
    }

    public function test_message_accepts_more_than_ten_attachments_and_files_over_ten_megabytes(): void
    {
        Storage::fake('local');

        $user = $this->employeeUser($this->alpha);
        $attachments = [
            UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf'),
        ];

        for ($index = 1; $index <= 10; $index++) {
            $attachments[] = UploadedFile::fake()->create(
                "attachment-{$index}.pdf",
                1,
                'application/pdf',
            );
        }

        Livewire::actingAs($user)
            ->test(GlobalChatRoom::class)
            ->set('attachments', $attachments)
            ->call('addMessage')
            ->assertHasNoErrors();

        $message = GlobalChatMessage::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(11, $message->attachments()->count());
    }

    public function test_administrators_can_access_every_active_company_room(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test(GlobalChatRoom::class)
            ->assertSee('Company Alpha')
            ->assertSee('Company Beta')
            ->call('selectCompany', $this->beta->id)
            ->assertSee('Company Beta');
    }

    public function test_private_broadcast_channel_respects_company_membership(): void
    {
        config()->set('broadcasting.default', 'reverb');
        config()->set('broadcasting.connections.reverb.key', 'global-chat-test-key');
        config()->set('broadcasting.connections.reverb.secret', 'global-chat-test-secret');
        config()->set('broadcasting.connections.reverb.app_id', 'global-chat-test-app');

        require base_path('routes/channels.php');

        $alphaUser = $this->employeeUser($this->alpha);
        $betaUser = $this->employeeUser($this->beta);
        $crossCompanyUser = User::factory()->create();
        Employee::query()->create([
            'user_id' => $crossCompanyUser->id,
            'name' => $crossCompanyUser->name,
            'is_active' => true,
        ])->permitCompanies()->attach([$this->alpha->id, $this->beta->id]);

        $this->actingAs($alphaUser)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-global-chat.company.'.$this->alpha->id,
            ])
            ->assertOk();

        $this->actingAs($betaUser)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-global-chat.company.'.$this->alpha->id,
            ])
            ->assertForbidden();

        $this->actingAs($crossCompanyUser)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-global-chat.company.'.$this->beta->id,
            ])
            ->assertOk();
    }

    public function test_chat_page_is_available_in_the_authenticated_panel(): void
    {
        $user = $this->employeeUser($this->alpha);

        $this->actingAs($user)
            ->get('/panel/global-chat-room')
            ->assertOk()
            ->assertSee('Chat Room Global')
            ->assertSee('Layanan real-time belum disiapkan.');
    }

    private function employeeUser(PermitCompany $company): User
    {
        $user = User::factory()->create();

        Employee::query()->create([
            'user_id' => $user->id,
            'name' => $user->name,
            'is_active' => true,
        ])->permitCompanies()->attach($company);

        return $user;
    }
}
