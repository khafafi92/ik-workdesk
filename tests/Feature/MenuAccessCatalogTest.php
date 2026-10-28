<?php

namespace Tests\Feature;

use App\Filament\Pages\AiAssistantChat;
use App\Filament\Pages\AiAssistantSettingsPage;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\GlobalChatRoom;
use App\Filament\Pages\ScmApprovals;
use App\Filament\Pages\SignatureProfile;
use App\Filament\Resources\Departments\DepartmentResource;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\Reports\ServiceDeskReportResource;
use App\Filament\Resources\TaskCategories\TaskCategoryResource;
use App\Filament\Resources\TicketCategories\TicketCategoryResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\WorkLocations\WorkLocationResource;
use App\Livewire\TicketCollaborationRoom;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Services\UserAdditionalAccessService;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\MenuAccessPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class MenuAccessCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertEmpty(config('database.connections.sqlite.url'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->seed(AccessControlSeeder::class);
    }

    public function test_every_visible_navigation_item_has_a_checklist_choice_or_an_explicit_access_rule(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $service = app(UserAdditionalAccessService::class);
        $groups = collect($service->menuGroups())->mapWithKeys(fn (array $group): array => [$group['label'] => array_values($service->menuOptionsForModule($group))]);
        $exceptions = [
            Dashboard::class => 'Default page for signed-in users',
            AiAssistantChat::class => 'Signed-in users, own conversations',
            GlobalChatRoom::class => 'Signed-in users, company scope',
            AiAssistantSettingsPage::class => 'System administrator only',
            UserResource::class => 'Administrator access level',
            ScmApprovals::class => 'Assigned PR/SR or CBE approval, SCM processor, or system administrator',
            SignatureProfile::class => 'SCM requester, processor or assigned approver',
        ];
        $aliases = ['Permintaan' => ['PR' => 'PR dan SR', 'SR' => 'PR dan SR'], 'SCM' => ['SCM' => 'Proses Pengadaan SCM'], 'Infrastruktur IT' => [
            'Master Perangkat' => 'Lihat Master Perangkat',
            'Pengecekan Infrastruktur' => 'Lihat Pengecekan',
            'Monitoring Harian IT' => 'Lihat Pengecekan',
            'Rekap & Laporan' => 'Lihat Rekap & Laporan',
        ]];
        $panel = Filament::getCurrentPanel();
        foreach ([...$panel->getResources(), ...$panel->getPages()] as $class) {
            if (! $class::shouldRegisterNavigation() || isset($exceptions[$class])) {
                continue;
            }
            $group = $class::getNavigationGroup() ?? 'Master Data';
            $label = $class::getNavigationLabel();
            $this->assertContains($aliases[$group][$label] ?? $label, $groups[$group] ?? [], "Missing checklist entry: {$class}");
        }
    }

    public function test_each_new_master_choice_opens_only_its_own_master(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user);
        $resources = [
            'master-departments' => DepartmentResource::class,
            'master-employees' => EmployeeResource::class,
            'master-work-locations' => WorkLocationResource::class,
        ];
        $service = app(UserAdditionalAccessService::class);
        foreach ($resources as $group => $resource) {
            $service->sync($user, [$group]);
            foreach ($resources as $other => $otherResource) {
                $this->assertSame($other === $group, $otherResource::canViewAny());
                $this->get($otherResource::getUrl())->assertStatus($other === $group ? 200 : 403);
            }
            $this->assertFalse($user->hasPermission('master-data.manage'));
            $this->assertFalse(UserResource::canViewAny());
        }
    }

    public function test_legacy_master_access_survives_loading_and_saving_the_checklist(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user);
        $service = app(UserAdditionalAccessService::class);
        $service->sync($user, ['master-data']);
        $resources = [DepartmentResource::class, EmployeeResource::class, WorkLocationResource::class,
            TicketCategoryResource::class, TaskCategoryResource::class];
        foreach ($resources as $resource) {
            $this->assertTrue($resource::canViewAny());
        }

        $service->sync($user, $service->accessGroupsFromMenuState($service->menuStateFor($user)));
        foreach ($resources as $resource) {
            $this->assertTrue($resource::canViewAny());
        }
        $this->assertFalse(UserResource::canViewAny());
    }

    public function test_new_action_choices_save_and_round_trip_without_changing_the_user_role(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false]);
        $user->roles()->attach(Role::query()->where('code', 'requester')->value('id'));
        $roleIds = $user->roles()->pluck('roles.id')->all();
        $state = ['service_desk' => ['service-desk', 'service-desk-manage', 'service-desk-comments'],
            'reports' => ['report-service-desk', 'report-export'],
            'daily_reports' => ['work-logs', 'findings-view', 'findings-manage']];

        Livewire::actingAs($admin)->test(EditUser::class, ['record' => $user->id])
            ->fillForm(['access_level' => 'requester', 'menu_access' => $state])
            ->call('save')->assertHasNoFormErrors();
        $user = $user->fresh();
        foreach (['tickets.manage', 'comments.create', 'report.export', 'findings.view', 'findings.manage'] as $permission) {
            $this->assertTrue($user->hasPermission($permission));
        }
        $this->assertSame($roleIds, $user->roles()->pluck('roles.id')->all());
        $actual = app(UserAdditionalAccessService::class)->menuStateFor($user);
        foreach ($state as $group => $choices) {
            $this->assertSame($choices, $actual[$group]);
        }
        $this->assertFalse($user->hasPermission('legal-tasks.approve'));
        $this->assertFalse($user->hasPermission('users.manage'));
    }

    public function test_manage_service_desk_keeps_department_boundaries_and_export_needs_an_accessible_report(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $department = Department::query()->create(['code' => 'HR', 'name' => 'Human Resources']);
        Employee::query()->create(['user_id' => $user->id, 'department_id' => $department->id, 'name' => 'HR pengujian', 'is_active' => true]);
        $own = Ticket::query()->create(['ticket_no' => 'CATALOG-1', 'handler_department_id' => $department->id,
            'employee_id' => $user->employee->id, 'requester_department_id' => $department->id, 'subject' => 'Pengujian']);
        $outside = Ticket::query()->create(['ticket_no' => 'CATALOG-2', 'subject' => 'Di luar department']);
        $service = app(UserAdditionalAccessService::class);
        $this->actingAs($user);
        $service->sync($user, ['service-desk']);
        $this->assertFalse(TicketResource::canEdit($own));
        $room = new TicketCollaborationRoom;
        $this->assertFalse($room->canPostMessage($own));
        $service->sync($user, ['service-desk', 'service-desk-comments']);
        $this->assertTrue($room->canPostMessage($own));
        $this->assertFalse($room->canPostMessage($outside));
        $service->sync($user, ['service-desk-manage', 'report-export']);
        $this->assertTrue(TicketResource::canEdit($own));
        $this->assertFalse(TicketResource::canView($outside));
        $this->assertFalse(ServiceDeskReportResource::canViewAny());
        $this->get(ServiceDeskReportResource::getUrl())->assertForbidden();
        $service->sync($user, ['report-service-desk']);
        $this->assertTrue(ServiceDeskReportResource::canViewAny());
        $this->assertFalse(ServiceDeskReportResource::canExport());
        $service->sync($user, ['report-service-desk', 'report-export']);
        $this->assertTrue(ServiceDeskReportResource::canExport());
        $service->sync($user, []);
        $this->assertFalse(TicketResource::canViewAny());
        $this->get(TicketResource::getUrl())->assertForbidden();
        $this->assertFalse(ServiceDeskReportResource::canExport());
    }

    public function test_catalog_seeding_is_idempotent_and_does_not_assign_access_to_existing_accounts(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $role = Role::query()->where('code', 'requester')->firstOrFail();
        $user->roles()->attach($role);
        $before = $role->permissions()->pluck('permissions.id')->all();
        $this->seed(MenuAccessPermissionSeeder::class);
        $this->seed(MenuAccessPermissionSeeder::class);

        $this->assertSame(3, Permission::query()->whereIn('code', ['master.departments.manage', 'master.employees.manage', 'master.work-locations.manage'])->count());
        $this->assertSame($before, $role->permissions()->pluck('permissions.id')->all());
        $this->assertSame(0, $user->directPermissions()->count());
    }
}
