<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentNumberingTemplates\DocumentNumberingTemplateResource;
use App\Filament\Resources\LetterProfiles\LetterProfileResource;
use App\Filament\Resources\OutgoingLetters\OutgoingLetterResource;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\UserAdditionalAccessService;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserAdditionalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_optional_menu_access_can_be_enabled_and_disabled_per_user(): void
    {
        foreach ([
            'meeting-bookings.view',
            'meeting-bookings.create',
            'meeting-bookings.cancel-own',
            'vehicle-bookings.view',
            'vehicle-bookings.create',
            'vehicle-bookings.cancel-own',
            'attendance.view',
            'report.view',
            'report.export',
        ] as $code) {
            Permission::query()->updateOrCreate([
                'code' => $code,
            ], [
                'name' => $code,
                'is_active' => true,
            ]);
        }

        $user = User::factory()->create(['is_admin' => false]);
        $service = app(UserAdditionalAccessService::class);

        $service->sync($user, ['meeting-room', 'attendance-report', 'reports']);

        $this->assertTrue($user->hasPermission('meeting-bookings.view'));
        $this->assertTrue($user->hasPermission('meeting-bookings.create'));
        $this->assertTrue($user->hasPermission('attendance.view'));
        $this->assertTrue($user->hasPermission('report.view'));
        $this->assertTrue($user->hasPermission('report.export'));
        $this->assertFalse($user->hasPermission('vehicle-bookings.view'));
        $this->assertEqualsCanonicalizing(
            ['meeting-room', 'attendance-report', 'reports'],
            $service->stateFor($user)
        );

        $service->sync($user, []);

        $this->assertFalse($user->hasPermission('meeting-bookings.view'));
        $this->assertFalse($user->hasPermission('attendance.view'));
        $this->assertFalse($user->hasPermission('report.view'));
    }

    public function test_requester_can_receive_selected_master_and_atk_menu_access_without_changing_role(): void
    {
        app(AccessControlSeeder::class)->run();
        $user = User::factory()->create(['is_admin' => false]);
        $user->roles()->attach(Role::query()->where('code', 'requester')->value('id'));

        $this->assertFalse($user->hasPermission('tickets.create'));
        $this->assertFalse($user->hasPermission('worklogs.view'));
        $this->assertFalse($user->hasPermission('master-data.manage'));

        app(UserAdditionalAccessService::class)->sync($user, ['master-data', 'atk-request']);

        $this->assertTrue($user->hasPermission('master-data.manage'));
        $this->assertTrue($user->hasPermission('atk.request'));
        $this->assertFalse($user->hasPermission('atk.manage'));
    }

    public function test_system_administrator_has_optional_access_without_checkboxes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertTrue($admin->hasPermission('meeting-bookings.view'));
        $this->assertTrue($admin->hasPermission('vehicle-bookings.view'));
        $this->assertTrue($admin->hasPermission('attendance.view'));
        $this->assertTrue($admin->hasPermission('report.view'));
    }

    public function test_surat_checklist_keeps_each_submenu_independent(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $service = app(UserAdditionalAccessService::class);

        $service->sync($user, $service->accessGroupsFromMenuState([
            'letters' => ['outgoing-letters'],
        ]));

        $this->assertTrue($user->hasPermission('letters.outgoing'));
        $this->assertTrue($user->hasPermission('letters.create'));
        $this->assertFalse($user->hasPermission('letters.document-types'));
        $this->assertFalse($user->hasPermission('letters.numbering-templates'));
        $this->assertSame(['outgoing-letters'], $service->menuStateFor($user)['letters']);
        $this->actingAs($user)->get('/panel/outgoing-letters')->assertOk();
        $this->actingAs($user)->get('/panel/document-types')->assertForbidden();
    }

    public function test_surat_checklist_includes_each_letter_register_as_an_independent_submenu(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $service = app(UserAdditionalAccessService::class);

        $service->sync($user, $service->accessGroupsFromMenuState([
            'letters' => ['hr-kpmog-letters'],
        ]));

        $this->assertTrue($user->hasPermission('letters.hr-kpmog'));
        $this->assertTrue($user->hasPermission('letters.view'));
        $this->assertFalse($user->hasPermission('letters.hr-apca'));
        $this->assertFalse($user->hasPermission('letters.kpmog-project-bd'));
        $this->assertFalse($user->hasPermission('letters.ate-general'));
        $this->assertSame(['hr-kpmog-letters'], $service->menuStateFor($user)['letters']);

        $this->actingAs($user)->get('/panel/hr-kpmog-letters')->assertOk();
        $this->actingAs($user)->get('/panel/hr-apca-letters')->assertForbidden();
        $this->actingAs($user)->get('/panel/kpmog-project-letters')->assertForbidden();
        $this->actingAs($user)->get('/panel/ate-general-letters')->assertForbidden();
    }

    public function test_surat_submenus_selected_in_user_setup_appear_in_navigation(): void
    {
        app(AccessControlSeeder::class)->run();
        $administrator = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($administrator)
            ->test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm([
                'access_level' => 'requester',
                'menu_access' => [
                    'letters' => [
                        'outgoing-letters',
                        'letter-profiles',
                        'document-numbering-templates',
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->actingAs($user);

        $this->assertTrue($user->hasPermission('letters.outgoing'));
        $this->assertTrue($user->hasPermission('letters.profiles'));
        $this->assertTrue($user->hasPermission('letters.numbering-templates'));
        $this->assertTrue(OutgoingLetterResource::shouldRegisterNavigation());
        $this->assertTrue(LetterProfileResource::shouldRegisterNavigation());
        $this->assertTrue(DocumentNumberingTemplateResource::shouldRegisterNavigation());

        $this->get('/panel/outgoing-letters')->assertOk();
        $this->get('/panel/letter-profiles')->assertOk();
        $this->get('/panel/document-numbering-templates')->assertOk();
    }
}
