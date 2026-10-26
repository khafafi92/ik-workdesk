<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\WorkTasks\WorkTaskResource;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Scm\ApprovalRoute;
use App\Models\User;
use App\Models\WorkTask;
use App\Notifications\LegalTaskApprovalRequestedNotification;
use App\Services\Scm\Access;
use App\Services\UserAccessHierarchyService;
use App\Services\UserAdditionalAccessService;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ScmSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ManagementAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->seed(AccessControlSeeder::class);
        $this->seed(ScmSeeder::class);
    }

    private function management(): User
    {
        $user = User::factory()->create(['is_admin' => false]);
        app(UserAccessHierarchyService::class)->syncPrimaryRole($user, 'management');

        return $user;
    }

    public function test_management_is_one_assignable_level_without_automatic_legal_or_administration_access(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $options = app(UserAccessHierarchyService::class)->optionsFor($actor);
        $this->assertArrayHasKey('management', $options);
        $this->assertArrayNotHasKey('cbo', $options);
        $user = $this->management();
        foreach (['legal-tasks.approve', 'users.manage', 'tickets.manage', 'worklogs.manage', 'scm.process', 'scm.configure'] as $code) {
            $this->assertFalse($user->hasPermission($code), $code);
        }
        $this->actingAs($user);
        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(WorkTaskResource::canViewAny());
        $this->get('/panel/users')->assertForbidden();
    }

    public function test_administrator_can_set_management_and_revoke_legal_approval_using_the_same_user_form(): void
    {
        $actor = User::factory()->create(['is_admin' => false]);
        $actor->roles()->attach(Role::where('code', 'administrator')->value('id'));
        $user = User::factory()->create(['is_admin' => false]);
        app(UserAccessHierarchyService::class)->syncPrimaryRole($user, 'requester');
        Livewire::actingAs($actor)->test(EditUser::class, ['record' => $user->id])
            ->fillForm(['access_level' => 'management', 'menu_access' => ['approvals' => ['legal-approval']]])
            ->call('save')->assertHasNoFormErrors();
        $user = $user->fresh();
        $this->assertSame('management', $user->access_level);
        $this->assertSame(['management'], $user->roles()->pluck('code')->all());
        $this->assertTrue($user->hasPermission('legal-tasks.approve'));
        $this->assertFalse($user->hasPermission('worklogs.manage'));
        $this->assertFalse($user->hasPermission('users.manage'));
        $this->assertSame(['legal-approval'], app(UserAdditionalAccessService::class)->menuStateFor($user)['approvals']);
        Livewire::actingAs($actor)->test(EditUser::class, ['record' => $user->id])
            ->assertFormSet(['access_level' => 'management', 'menu_access.approvals' => ['legal-approval']])
            ->fillForm(['menu_access' => ['approvals' => []]])
            ->call('save')->assertHasNoFormErrors();
        $this->assertFalse($user->fresh()->hasPermission('legal-tasks.approve'));
        $this->assertSame('management', $user->fresh()->access_level);
    }

    public function test_new_coo_account_can_use_management_without_receiving_legal_approval(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $department = Department::create(['code' => 'MGMT-NEW', 'name' => 'Management']);
        $employee = Employee::create(['name' => 'New COO II', 'department_id' => $department->id, 'is_active' => true]);
        Livewire::actingAs($actor)->test(CreateUser::class)
            ->fillForm(['employee_id' => $employee->id, 'name' => 'New COO II', 'email' => 'coo.new@example.test',
                'password' => 'QA-management-test', 'access_level' => 'management', 'menu_access' => []])
            ->call('create')->assertHasNoFormErrors();
        $user = User::where('email', 'coo.new@example.test')->sole();
        $this->assertSame('management', $user->access_level);
        $this->assertSame($user->id, $employee->fresh()->user_id);
        $this->assertTrue($user->hasRole('management'));
        $this->assertFalse($user->hasPermission('legal-tasks.approve'));
        $this->assertFalse($user->hasPermission('users.manage'));
    }

    public function test_management_legal_approver_receives_and_decides_legal_requests_while_coo_does_not(): void
    {
        $cbo = $this->management();
        $coo = $this->management();
        app(UserAdditionalAccessService::class)->sync($cbo, ['legal-approval']);
        $department = Department::create(['code' => 'LEGAL-MGMT', 'name' => 'Legal', 'is_active' => true]);
        $task = WorkTask::create(['task_no' => 'MGMT-LEGAL-1', 'department_id' => $department->id, 'title' => 'Review Legal', 'status' => 'planned']);
        $this->assertTrue($task->isAwaitingLegalApproval());
        Notification::assertSentTo($cbo, LegalTaskApprovalRequestedNotification::class);
        Notification::assertNotSentTo($coo, LegalTaskApprovalRequestedNotification::class);
        $this->assertFalse($task->canBeApprovedBy($coo));
        $this->actingAs($cbo);
        $this->assertTrue(WorkTaskResource::shouldRegisterNavigation());
        $this->assertTrue(WorkTaskResource::canView($task));
        $this->assertSame([$task->id], WorkTaskResource::getEloquentQuery()->pluck('work_tasks.id')->all());
        $task->approveLegalTask($cbo);
        $this->assertSame('approved', $task->fresh()->approval_status);
        $this->assertSame($cbo->id, $task->fresh()->approved_by_user_id);
        $this->assertTrue(WorkTaskResource::getEloquentQuery()->whereKey($task)->exists());
        app(UserAdditionalAccessService::class)->sync($cbo, []);
        $this->assertFalse(WorkTaskResource::canViewAny());
    }

    public function test_migration_moves_existing_cbo_permissions_to_checklists_without_changing_other_access(): void
    {
        $legacy = Role::where('code', 'cbo')->sole();
        $cbo = User::factory()->create(['is_admin' => false, 'access_level' => 'cbo']);
        $otherRole = Role::create(['code' => 'additional-reviewer', 'name' => 'Additional Reviewer', 'is_active' => true]);
        $cbo->roles()->attach([$legacy->id, $otherRole->id]);
        app(UserAdditionalAccessService::class)->sync($cbo, ['scm-requests']);
        $coo = $this->management();
        $migration = require database_path('migrations/2026_10_26_110000_add_management_access_level.php');
        $migration->up();
        $migration->up();
        $cbo = $cbo->fresh();
        $this->assertSame('management', $cbo->access_level);
        $this->assertTrue($cbo->hasRole('management'));
        $this->assertTrue($cbo->hasRole('additional-reviewer'));
        $this->assertFalse($cbo->hasRole('cbo'));
        $this->assertTrue($cbo->hasPermission('legal-tasks.approve'));
        $this->assertTrue($cbo->hasPermission('scm.request'));
        $this->assertFalse($coo->fresh()->hasPermission('legal-tasks.approve'));
        $state = app(UserAdditionalAccessService::class)->menuStateFor($cbo);
        $this->assertSame(['legal-approval'], $state['approvals']);
        $state['approvals'] = [];
        app(UserAdditionalAccessService::class)->sync($cbo, app(UserAdditionalAccessService::class)->accessGroupsFromMenuState($state));
        $this->assertFalse($cbo->hasPermission('legal-tasks.approve'));
        $this->assertTrue($cbo->hasPermission('scm.request'));
    }

    public function test_migration_does_not_activate_permissions_from_an_inactive_cbo_role_or_a_level_without_that_role(): void
    {
        $legacy = Role::where('code', 'cbo')->sole();
        $legacy->update(['is_active' => false]);
        $user = User::factory()->create(['is_admin' => false, 'access_level' => 'cbo']);
        $user->roles()->attach($legacy);
        $withoutRole = User::factory()->create(['is_admin' => false, 'access_level' => 'cbo']);
        $migration = require database_path('migrations/2026_10_26_110000_add_management_access_level.php');
        $migration->up();
        foreach ([$user, $withoutRole] as $account) {
            $this->assertSame('management', $account->fresh()->access_level);
            $this->assertFalse($account->fresh()->hasPermission('legal-tasks.approve'));
        }
    }

    public function test_assigned_scm_approvals_survive_management_level_changes_and_legal_checkbox_changes(): void
    {
        $cbo = $this->management();
        $coo = $this->management();
        $other = $this->management();
        $department = Department::create(['code' => 'MGMT-SCM', 'name' => 'Management SCM']);
        ApprovalRoute::create(['company' => 'APCA', 'department_id' => $department->id, 'acknowledger_id' => $other->id,
            'cbo_id' => $cbo->id, 'cbo3_id' => $coo->id, 'version' => 0]);
        foreach ([$cbo, $coo] as $user) {
            app(UserAccessHierarchyService::class)->syncPrimaryRole($user, 'management');
            app(UserAdditionalAccessService::class)->sync($user, ['legal-approval']);
            $this->assertTrue(Access::approves($user));
            app(UserAdditionalAccessService::class)->sync($user, []);
            $this->assertTrue(Access::approves($user));
            $this->assertTrue(Access::signs($user));
            $this->assertFalse(Access::manages($user));
        }
    }
}
