<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\UserAccessHierarchyService;
use App\Services\UserAdditionalAccessService;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAccessHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_receive_master_data_without_reports_or_management_menus(): void
    {
        $options = app(UserAdditionalAccessService::class)
            ->optionsForLevel('requester');

        $this->assertArrayHasKey('master-data', $options);
        $this->assertArrayHasKey('atk-request', $options);
        $this->assertArrayNotHasKey('reports', $options);
        $this->assertArrayNotHasKey('atk-management', $options);
        $this->assertArrayNotHasKey('daily-report', $options);
    }

    public function test_administrator_can_assign_lower_levels_but_not_sys_administrator(): void
    {
        app(AccessControlSeeder::class)->run();
        $administrator = User::factory()->create(['is_admin' => false]);
        $administrator->roles()->attach(
            Role::query()->where('code', 'administrator')->value('id')
        );

        $service = app(UserAccessHierarchyService::class);

        $this->assertTrue($service->canAssign($administrator, 'department-manager'));
        $this->assertTrue($service->canAssign($administrator, 'requester'));
        $this->assertFalse($service->canAssign($administrator, 'system-admin'));
    }

    public function test_primary_level_replaces_previous_primary_role(): void
    {
        app(AccessControlSeeder::class)->run();
        $user = User::factory()->create(['is_admin' => false]);
        $user->roles()->attach([
            Role::query()->where('code', 'requester')->value('id'),
            Role::query()->where('code', 'supervisor')->value('id'),
        ]);

        app(UserAccessHierarchyService::class)->syncPrimaryRole(
            $user,
            'department-manager'
        );

        $this->assertSame('department-manager', $user->fresh()->access_level);
        $this->assertSame(
            ['department-manager'],
            $user->fresh()->roles()->pluck('code')->all()
        );
    }
}
