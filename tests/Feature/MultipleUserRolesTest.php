<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultipleUserRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_multiple_roles_and_user_receives_combined_access(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false]);

        $approveLegal = Permission::query()
            ->where('code', 'legal-tasks.approve')
            ->firstOrFail();
        $manageWorklogs = Permission::query()->create([
            'name' => 'Manage Work Logs',
            'code' => 'worklogs.manage',
            'is_active' => true,
        ]);
        $cbo = Role::query()->where('code', 'cbo')->firstOrFail();
        $manager = Role::query()->create([
            'name' => 'Manager',
            'code' => 'department-manager',
            'is_active' => true,
        ]);
        $cbo->permissions()->syncWithoutDetaching($approveLegal);
        $manager->permissions()->attach($manageWorklogs);

        $roleIds = app(RoleAssignmentService::class)
            ->filterAssignableRoleIds($admin, [$cbo->id, $manager->id]);

        $this->assertCount(2, $roleIds);

        $user->roles()->sync($roleIds);
        $user->unsetRelation('roles');

        $this->assertTrue($user->hasRole('cbo'));
        $this->assertTrue($user->hasRole('department-manager'));
        $this->assertTrue($user->hasPermission('legal-tasks.approve'));
        $this->assertTrue($user->hasPermission('worklogs.manage'));
    }
}
