<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\UserAdditionalAccessService;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentRoleHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_and_supervisor_receive_menu_access_only_when_selected(): void
    {
        $this->seed(AccessControlSeeder::class);

        $manager = Role::query()
            ->where('code', 'department-manager')
            ->firstOrFail();
        $supervisor = Role::query()
            ->where('code', 'supervisor')
            ->firstOrFail();

        $this->assertFalse($manager->permissions->contains('code', 'tickets.manage'));
        $this->assertFalse($manager->permissions->contains('code', 'worklogs.manage'));
        $this->assertTrue($manager->permissions->contains('code', 'findings.manage'));

        $this->assertFalse($supervisor->permissions->contains('code', 'worklogs.manage'));
        $this->assertFalse($supervisor->permissions->contains('code', 'tickets.manage'));
        $this->assertFalse(Role::query()->where('code', 'department-reviewer')->exists());

        $managerUser = User::factory()->create(['is_admin' => false]);
        $managerUser->roles()->attach($manager);
        $supervisorUser = User::factory()->create(['is_admin' => false]);
        $supervisorUser->roles()->attach($supervisor);
        $service = app(UserAdditionalAccessService::class);
        $service->sync($managerUser, ['service-desk-manage', 'daily-report']);
        $service->sync($supervisorUser, ['daily-report']);

        $this->assertTrue($managerUser->hasPermission('tickets.manage'));
        $this->assertTrue($managerUser->hasPermission('worklogs.manage'));
        $this->assertTrue($supervisorUser->hasPermission('worklogs.manage'));
        $this->assertFalse($supervisorUser->hasPermission('tickets.manage'));
    }
}
