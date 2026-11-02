<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentService;
use App\Services\Scm\Access;
use App\Services\UserAccessHierarchyService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SystemAdministratorAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function rootTypes(): array
    {
        return [['flag'], ['role']];
    }

    #[DataProvider('rootTypes')]
    public function test_active_superuser_has_global_authority_without_module_grants(string $type): void
    {
        $root = User::factory()->create(['is_admin' => $type === 'flag']);
        if ($type === 'role') {
            $role = Role::create(['code' => 'system-admin', 'name' => 'Sys Administrator', 'is_active' => true]);
            $root->roles()->attach($role);
        }
        $department = Department::create(['code' => 'ROOT-TEST', 'name' => 'Other department']);
        $other = User::factory()->create(['is_admin' => false]);
        $this->assertTrue($root->isSystemAdministrator());
        foreach (['scm.request', 'scm.process', 'scm.configure', 'master-data.manage', 'tickets.manage'] as $permission) {
            $this->assertTrue($root->hasPermission($permission));
        }
        $this->assertTrue($root->canAccessDepartment($department->id));
        $this->assertContains($department->id, $root->accessibleDepartmentIds());
        $this->assertTrue(Gate::forUser($root)->allows('update', $other));
        $this->assertTrue(Gate::forUser($root)->allows('delete', $other));
        $this->assertFalse(Gate::forUser($other)->allows('update', $root));
        $this->assertSame('system-admin', app(UserAccessHierarchyService::class)->levelFor($root));
        $this->assertTrue(app(UserAccessHierarchyService::class)->canAssign($root, 'system-admin'));
        $this->assertTrue(Access::has($root, 'scm.configure'));
        $this->assertTrue(Access::canChooseRequester($root, 'SR'));
        Employee::create(['user_id' => $root->id, 'name' => $root->name, 'department_id' => $department->id, 'is_active' => false]);
        $root = $root->fresh();
        $this->assertFalse($root->isSystemAdministrator());
        $this->assertFalse($root->hasPermission('scm.configure'));
        $this->assertFalse(Gate::forUser($root)->allows('update', $other));
        $this->assertFalse(Access::has($root, 'scm.configure'));
    }

    public function test_disabled_system_role_does_not_grant_override(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $role = Role::create(['code' => 'system-admin', 'name' => 'Sys Administrator', 'is_active' => false]);
        $user->roles()->attach($role);
        $this->assertFalse($user->isSystemAdministrator());
        $this->assertFalse($user->hasPermission('scm.configure'));
        $this->assertFalse(Access::canChooseRequester($user, 'PR'));
    }

    public function test_role_based_superuser_can_create_and_update_another_system_administrator(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $role = Role::create(['code' => 'system-admin', 'name' => 'Sys Administrator', 'is_active' => true]);
        $root = User::factory()->create(['is_admin' => false]);
        $root->roles()->attach($role);
        $employee = Employee::create(['name' => 'DEMO new administrator', 'is_active' => true]);
        $this->assertSame([$role->id], app(RoleAssignmentService::class)->filterAssignableRoleIds($root, [$role->id]));
        Livewire::actingAs($root)->test(CreateUser::class)->fillForm([
            'employee_id' => $employee->id, 'name' => $employee->name, 'email' => 'new-root@example.test',
            'password' => 'test-only-password', 'is_admin' => false, 'access_level' => 'system-admin',
        ])->call('create')->assertHasNoFormErrors();
        $created = User::where('email', 'new-root@example.test')->sole();
        $this->assertTrue($created->isSystemAdministrator());
        Livewire::actingAs($root)->test(EditUser::class, ['record' => $created->id])->fillForm([
            'name' => 'DEMO updated administrator', 'access_level' => 'system-admin',
        ])->call('save')->assertHasNoFormErrors();
        $this->assertTrue($created->fresh()->isSystemAdministrator());
        $this->assertSame('DEMO updated administrator', $created->fresh()->name);
    }
}
