<?php

namespace Tests\Feature;

use App\Models\Role;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentRoleHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_manage_department_work_and_supervisor_is_operational(): void
    {
        $this->seed(AccessControlSeeder::class);

        $manager = Role::query()
            ->where('code', 'department-manager')
            ->firstOrFail();
        $supervisor = Role::query()
            ->where('code', 'supervisor')
            ->firstOrFail();

        $this->assertTrue($manager->permissions->contains('code', 'tickets.manage'));
        $this->assertTrue($manager->permissions->contains('code', 'worklogs.manage'));
        $this->assertTrue($manager->permissions->contains('code', 'findings.manage'));

        $this->assertTrue($supervisor->permissions->contains('code', 'worklogs.manage'));
        $this->assertFalse($supervisor->permissions->contains('code', 'tickets.manage'));
        $this->assertFalse(Role::query()->where('code', 'department-reviewer')->exists());
    }
}
