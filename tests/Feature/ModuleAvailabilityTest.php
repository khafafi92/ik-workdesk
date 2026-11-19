<?php

namespace Tests\Feature;

use App\Filament\Pages\AiAssistantChat;
use App\Filament\Pages\ModuleAccessSettingsPage;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\User;
use App\Services\ModuleAccessService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ModuleAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_only_system_administrators_can_open_module_settings(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(ModuleAccessSettingsPage::getUrl())
            ->assertForbidden();

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(ModuleAccessSettingsPage::getUrl())
            ->assertOk()
            ->assertSee('Collaboration')
            ->assertSee('Pengadaan');

        $visibleGroups = collect(Filament::getCurrentOrDefaultPanel()->getNavigation())
            ->map(fn ($group): ?string => $group->getLabel())
            ->all();

        $this->assertContains('Collaboration', $visibleGroups);
        $this->assertContains('Pengaturan Sistem', $visibleGroups);
    }

    public function test_administrator_can_disable_a_module_for_everyone_and_keep_settings_access(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $module = collect(app(ModuleAccessService::class)->modules())
            ->firstWhere('label', 'Collaboration');

        $this->assertNotNull($module);

        Livewire::actingAs($admin)
            ->test(ModuleAccessSettingsPage::class)
            ->set("data.modules.{$module['key']}", false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('module_availabilities', [
            'module_key' => $module['key'],
            'is_enabled' => false,
        ]);

        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)
            ->get(AiAssistantChat::getUrl())
            ->assertForbidden();

        $visibleGroups = collect(Filament::getCurrentOrDefaultPanel()->getNavigation())
            ->map(fn ($group): ?string => $group->getLabel())
            ->all();
        $this->assertNotContains('Collaboration', $visibleGroups);

        $this->actingAs($admin)
            ->get(AiAssistantChat::getUrl())
            ->assertForbidden();

        $this->app->forgetScopedInstances();

        $visibleGroups = collect(Filament::getCurrentOrDefaultPanel()->getNavigation())
            ->map(fn ($group): ?string => $group->getLabel())
            ->all();

        $this->assertNotContains('Collaboration', $visibleGroups);
        $this->assertContains('Pengaturan Sistem', $visibleGroups);
        $this->get(ModuleAccessSettingsPage::getUrl())->assertOk();
    }

    public function test_enabled_modules_still_require_the_existing_role_permissions(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(TicketResource::getUrl())
            ->assertForbidden();
    }

    public function test_unconfigured_modules_are_enabled_by_default(): void
    {
        $this->assertTrue(app(ModuleAccessService::class)->isEnabled('Collaboration'));
        $this->assertDatabaseCount('module_availabilities', 0);
    }
}
