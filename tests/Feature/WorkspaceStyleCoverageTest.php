<?php

namespace Tests\Feature;

use App\Filament\Pages\AiAssistantChat;
use App\Filament\Pages\AiAssistantSettingsPage;
use App\Filament\Pages\ScmProcurement;
use App\Filament\Pages\ScmRequestNumbers;
use App\Filament\Pages\SignatureProfile;
use App\Models\Department;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceStyleCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function assertWorkspacePage(string $url, int $status = 200): void
    {
        $this->get($url)
            ->assertStatus($status)
            ->assertSee('css/workdesk-workspace.css', false);
    }

    public function test_account_and_admin_crud_pages_load_the_shared_workspace_theme(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $department = Department::create(['code' => 'STYLE-QA', 'name' => 'Style QA', 'is_active' => true]);

        foreach (['/dashboard', '/profile', '/admin/departments', '/admin/departments/create', '/admin/departments/'.$department->id.'/edit'] as $url) {
            $this->assertWorkspacePage($url);
        }
    }

    public function test_filament_resource_forms_and_custom_modules_share_the_theme(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        foreach ([
            '/panel', '/panel/service-desk', '/panel/service-desk/create',
            '/panel/work-tasks', '/panel/work-tasks/create', '/panel/departments',
            '/panel/departments/create', '/panel/management-summary', '/panel/atk-dashboard',
            '/panel/attendance-report-center', '/panel/meeting-room-calendar', '/panel/vehicle-booking-calendar',
            AiAssistantChat::getUrl(), AiAssistantSettingsPage::getUrl(),
            ScmProcurement::getUrl(), ScmRequestNumbers::getUrl(), SignatureProfile::getUrl(),
        ] as $url) {
            $this->assertWorkspacePage($url);
        }
    }

    public function test_legacy_ltro_pages_load_the_same_theme_as_the_panel(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        foreach (['/ltro/monitoring', '/ltro/daily-input', '/ltro/availability'] as $url) {
            $this->assertWorkspacePage($url);
        }
    }

    public function test_authentication_vendor_and_unavailable_form_pages_load_the_theme(): void
    {
        foreach (['/login', '/forgot-password', '/reset-password/style-qa-token', '/vendor'] as $url) {
            $this->assertWorkspacePage($url);
        }

        $this->assertWorkspacePage('/vendor/form-005/'.str_repeat('a', 64).'/bagian/1', 404);
    }

    public function test_access_denied_and_missing_pages_keep_their_status_with_the_workspace_theme(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->assertWorkspacePage('/panel/roles', 403);
        $this->assertWorkspacePage('/workspace-style-missing-page', 404);
    }
}
