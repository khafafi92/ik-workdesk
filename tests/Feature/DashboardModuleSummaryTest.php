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

        $this->assertSame(['ATK', 'LTRO', 'Attendance Report'], array_column($summaries, 'title'));
        $this->get('/panel')->assertOk()
            ->assertSeeText('Ringkasan Modul')
            ->assertSeeText('Permintaan dan ketersediaan stok.')
            ->assertSeeText('Laporan operasi dan indikator keandalan.')
            ->assertSeeText('Data absensi yang sudah diimpor.');
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
        $this->get('/panel')->assertOk()->assertDontSeeText('Ringkasan Modul');
    }
}
