<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardModuleSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_only_module_summaries_granted_to_the_user(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $permissions = collect([
            'atk.manage',
            'ltro.view',
            'attendance.view',
        ])->mapWithKeys(fn (string $code): array => [$code => Permission::query()->firstOrCreate(
            ['code' => $code],
            ['name' => $code, 'is_active' => true],
        )->id]);
        $user->directPermissions()->attach($permissions->values());

        $this->actingAs($user);

        $summaries = app(Dashboard::class)->getDashboardData()['moduleSummaries'];

        $this->assertSame(['Office Supplies', 'LTRO', 'Attendance Report'], array_column($summaries, 'title'));
        $this->get('/panel')->assertOk()
            ->assertSeeText('Module Summary')
            ->assertSeeText('Track requests and stock availability.')
            ->assertSeeText('Operating reports and reliability metrics.')
            ->assertSeeText('Imported attendance records.');
    }

    public function test_atk_requesters_do_not_receive_warehouse_summary_metrics(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $permission = Permission::query()->firstOrCreate(
            ['code' => 'atk.request'],
            ['name' => 'atk.request', 'is_active' => true],
        );
        $user->directPermissions()->attach($permission);

        $this->actingAs($user);

        $this->assertSame([], app(Dashboard::class)->getDashboardData()['moduleSummaries']);
        $this->get('/panel')->assertOk()->assertDontSeeText('Module Summary');
    }

    public function test_dashboard_preferences_keep_only_sections_allowed_by_permission(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $permission = Permission::query()->firstOrCreate(
            ['code' => 'atk.manage'],
            ['name' => 'atk.manage', 'is_active' => true],
        );
        $user->directPermissions()->attach($permission);
        $user->unsetRelation('directPermissions');

        $this->actingAs($user);

        $dashboard = app(Dashboard::class);

        $this->assertSame([
            'atk' => 'Office Supplies',
            'reminders' => 'Reminder',
            'recent_activity' => 'Recent Service Desk tickets and work logs',
        ], $dashboard->getDashboardSectionOptions());

        $dashboard->updateDashboardSections([
            'atk',
            'ltro',
            'work_overview',
            'not-a-dashboard-section',
        ]);

        $this->assertSame(['atk'], $user->fresh()->dashboard_sections);
        $this->assertSame(['atk'], $dashboard->getVisibleDashboardSections());
        $this->assertSame(['Office Supplies'], array_column(
            $dashboard->getDashboardData()['moduleSummaries'],
            'title',
        ));
        $this->get('/panel')->assertOk()
            ->assertSeeText('Edit Dashboard')
            ->assertSeeText('Module Summary')
            ->assertDontSee('ik-reminder-hub', false);
    }
}
