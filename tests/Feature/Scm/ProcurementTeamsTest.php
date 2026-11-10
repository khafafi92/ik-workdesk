<?php

namespace Tests\Feature\Scm;

use App\Filament\Pages\PurchaseRequests;
use App\Filament\Pages\PurchasingOfficer;
use App\Filament\Pages\ScmProcurement;
use App\Filament\Pages\ServiceRequests;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Scm\Procurement;
use App\Models\User;
use App\Services\Scm\Access;
use App\Services\UserAdditionalAccessService;
use Database\Seeders\ScmSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProcurementTeamsTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    private User $requester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->seed(ScmSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->department = Department::create(['code' => 'TEAM-QA', 'name' => 'Departemen pengujian', 'is_active' => true]);
        $this->requester = $this->actor(['scm.request']);
    }

    private function actor(array $permissions): User
    {
        $user = User::factory()->create(['is_admin' => false]);
        Employee::create(['user_id' => $user->id, 'department_id' => $this->department->id, 'name' => $user->name, 'is_active' => true]);
        $user->directPermissions()->attach(Permission::whereIn('code', $permissions)->pluck('id'));

        return $user;
    }

    private function request(string $company, string $needType, string $kind = 'PR', string $status = 'submitted'): Procurement
    {
        return Procurement::create(['number' => 'QA-'.$company.'-'.$needType.'-'.$kind.'-'.Procurement::count(),
            'company' => $company, 'need_type' => $needType, 'kind' => $kind, 'date' => today(),
            'requester_id' => $this->requester->id, 'department_id' => $this->department->id,
            'title' => 'Permintaan pengujian', 'reason' => 'Pengujian akses tim', 'status' => $status]);
    }

    public function test_ga_can_process_both_ho_companies_but_cannot_read_or_mutate_projects(): void
    {
        $ga = $this->actor(['ga.process']);
        $ho = $this->request('KPMOG', 'HO');
        $otherHo = $this->request('APCA', 'HO', 'SR');
        $project = $this->request('KPMOG', 'PROJECT');
        $draft = $this->request('APCA', 'HO', status: 'draft');
        $this->assertTrue(Access::manages($ga, $ho));
        $this->assertTrue(Access::manages($ga, $otherHo));
        $this->assertFalse(Access::manages($ga, $project));
        $this->assertFalse(Access::manages($ga, $draft));
        $this->assertEqualsCanonicalizing([$ho->id, $otherHo->id], Access::scopeProcessing(Procurement::query(), $ga)->pluck('id')->all());
        $this->actingAs($ga);
        $this->assertFalse(PurchaseRequests::canAccess());
        $this->assertFalse(ScmProcurement::canAccess());
        $this->assertTrue(PurchasingOfficer::canAccess());
        $this->assertTrue(ScmProcurement::shouldRegisterNavigation());
        Livewire::test(PurchasingOfficer::class)->assertSee($ho->number)->assertSee($otherHo->number)->assertDontSee($project->number)
            ->call('open', $ho->id)->assertSee('Terima oleh Purchasing Officer')->call('receiveRequest')->assertHasNoErrors();
        $this->assertSame('sourcing', $ho->fresh()->status);
        $this->assertSame($ga->id, $ho->fresh()->scm_pic_id);
        Livewire::test(PurchasingOfficer::class)->call('open', $project->id)->assertForbidden();
        Livewire::test(PurchasingOfficer::class)->set('selected', $project->id)->set('version', $project->version)->call('receiveRequest')->assertNotFound();
        $this->assertSame('submitted', $project->fresh()->status);
        Livewire::test(PurchasingOfficer::class)->set('kindFilter', 'SR')->assertSee($otherHo->number)->assertDontSee($ho->number)
            ->call('open', $otherHo->id)->call('receiveRequest')->assertHasNoErrors();
        $this->assertSame('sourcing', $otherHo->fresh()->status);
    }

    public function test_project_permissions_are_limited_by_company_and_department(): void
    {
        $kpmog = $this->actor(['scm.process-kpmog']);
        $apca = $this->actor(['scm.process-apca']);
        $kpmogProject = $this->request('KPMOG', 'PROJECT');
        $apcaProject = $this->request('APCA', 'PROJECT', 'SR');
        $ho = $this->request('KPMOG', 'HO');
        $this->assertTrue(Access::manages($kpmog, $kpmogProject));
        $this->assertFalse(Access::manages($kpmog, $apcaProject));
        $this->assertFalse(Access::manages($kpmog, $ho));
        $this->assertTrue(Access::manages($apca, $apcaProject));
        $this->assertFalse(Access::manages($apca, $kpmogProject));
        $this->assertSame([$kpmogProject->id], Access::scope(Procurement::query(), $kpmog)->pluck('id')->all());
        Livewire::actingAs($apca)->test(ScmProcurement::class)->assertSee($apcaProject->number)
            ->assertDontSee($kpmogProject->number)->assertDontSee($ho->number)->call('open', $kpmogProject->id)->assertForbidden();
        Livewire::test(ScmProcurement::class)->call('open', $apcaProject->id)->assertSee('Terima oleh SCM')->call('receiveRequest')->assertHasNoErrors();
        $this->assertSame('sourcing', $apcaProject->fresh()->status);
        $otherDepartment = Department::create(['code' => 'OTHER-QA', 'name' => 'Departemen lain']);
        $apcaProject->update(['department_id' => $otherDepartment->id]);
        $this->assertFalse(Access::manages($apca, $apcaProject->fresh()));
    }

    public function test_processors_cannot_mutate_sr_from_the_pr_page_or_edit_requester_drafts(): void
    {
        $ga = $this->requester;
        $ga->directPermissions()->attach(Permission::where('code', 'ga.process')->sole()->id);
        $sr = $this->request('APCA', 'HO', 'SR');
        Livewire::actingAs($ga)->test(PurchaseRequests::class)->set('selected', $sr->id)
            ->set('version', $sr->version)->call('receiveRequest')->assertNotFound();
        Livewire::test(ServiceRequests::class)->call('open', $sr->id)->assertDontSee('Terima oleh Purchasing Officer')
            ->call('receiveRequest')->assertForbidden();
        $this->assertSame('submitted', $sr->fresh()->status);
        $draft = $this->request('KPMOG', 'HO', status: 'draft');
        Livewire::test(PurchasingOfficer::class)->assertDontSee($draft->number)->call('open', $draft->id)->assertForbidden();
        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_requesters_keep_private_requests_and_processors_can_filter_without_widening_scope(): void
    {
        $mine = $this->request('KPMOG', 'HO');
        $other = $this->request('APCA', 'HO');
        $other->update(['requester_id' => $this->actor(['scm.request'])->id]);
        $project = $this->request('APCA', 'PROJECT');
        $project->update(['requester_id' => $other->requester_id]);
        Livewire::actingAs($this->requester)->test(PurchaseRequests::class)->assertSee($mine->number)->assertDontSee($other->number)
            ->call('open', $mine->id)->assertDontSee('Terima oleh Purchasing Officer')->call('showTab', 'quotes')->assertForbidden();
        $ga = $this->actor(['ga.process']);
        Livewire::actingAs($ga)->test(PurchasingOfficer::class)->assertSee($mine->number)->assertSee($other->number)->assertDontSee($project->number)
            ->set('companyFilter', 'APCA')->assertSee($other->number)->assertDontSee($mine->number)
            ->set('needTypeFilter', 'PROJECT')->assertDontSee($project->number)->assertDontSee($other->number)
            ->call('clearFilters')->assertSee($mine->number)->assertSee($other->number)->assertDontSee($project->number);
    }

    public function test_user_management_exposes_scoped_team_access_and_legacy_access_stays_compatible(): void
    {
        $ga = $this->actor([]);
        app(UserAdditionalAccessService::class)->sync($ga, ['ga-process']);
        $this->assertTrue(Access::has($ga, 'ga.process'));
        $this->assertTrue(Access::has($ga, 'scm.request'));
        $this->assertFalse(Access::has($ga, 'scm.process'));
        $legacy = $this->actor(['scm.process']);
        $this->assertTrue(Access::manages($legacy, $this->request('KPMOG', 'HO')));
        $this->assertTrue(Access::manages($legacy, $this->request('APCA', 'PROJECT')));
        $ga->employee->update(['is_active' => false]);
        $ga->unsetRelation('employee');
        $this->assertFalse(Access::manages($ga));
    }

    public function test_role_permissions_route_ho_to_purchasing_officer(): void
    {
        $ga = $this->actor([]);
        $role = Role::create(['code' => 'ga-ho-test', 'name' => 'Petugas GA HO', 'is_active' => true]);
        $role->permissions()->attach(Permission::where('code', 'ga.process')->sole()->id);
        $ga->roles()->attach($role);
        $pr = $this->request('KPMOG', 'HO');
        $sr = $this->request('APCA', 'HO', 'SR');
        $project = $this->request('KPMOG', 'PROJECT');
        Livewire::actingAs($ga)->test(PurchasingOfficer::class)->assertSee($pr->number)->assertDontSee($project->number)
            ->call('open', $pr->id)->call('receiveRequest')->assertHasNoErrors()->assertSee('Diterima Purchasing Officer');
        Livewire::test(PurchasingOfficer::class)->assertSee($sr->number)->call('open', $sr->id)
            ->assertSee('Terima oleh Purchasing Officer')->call('receiveRequest')->assertHasNoErrors();
        $this->assertSame('submitted', $project->fresh()->status);
        $this->assertSame('sourcing', $pr->fresh()->status);
        $this->assertSame('sourcing', $sr->fresh()->status);
        $this->assertTrue(PurchasingOfficer::shouldRegisterNavigation());
    }

    public function test_configuration_stays_in_processing_menus_and_requires_configuration_permission(): void
    {
        $configurer = $this->actor(['scm.configure']);
        Livewire::actingAs($configurer)->test(ScmProcurement::class)->assertSet('section', 'settings')->assertSee('Master Approval');
        Livewire::test(PurchaseRequests::class)->assertForbidden();
        $ga = $this->actor(['ga.process']);
        Livewire::actingAs($ga)->test(PurchasingOfficer::class)->call('switchSection', 'settings')->assertForbidden();
    }

    public function test_admin_processing_menus_separate_need_types_and_exclude_unapproved_requests(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $ho = $this->request('APCA', 'HO');
        $project = $this->request('KPMOG', 'PROJECT', 'SR');
        $draft = $this->request('APCA', 'HO', status: 'draft');
        $approval = $this->request('KPMOG', 'PROJECT', status: 'approval');
        Livewire::actingAs($admin)->test(PurchasingOfficer::class)->assertSee($ho->number)->assertDontSee($project->number)
            ->assertDontSee($draft->number)->assertDontSee($approval->number)->call('open', $project->id)->assertForbidden();
        Livewire::test(ScmProcurement::class)->assertSee($project->number)->assertDontSee($ho->number)
            ->assertDontSee($draft->number)->assertDontSee($approval->number)->call('open', $ho->id)->assertForbidden();
        Livewire::test(ScmProcurement::class)->set('selected', $ho->id)->call('receiveRequest')->assertNotFound();
        $this->assertSame('submitted', $ho->fresh()->status);
        Livewire::test(PurchaseRequests::class)->call('open', $ho->id)->assertDontSee('Terima oleh Purchasing Officer')
            ->call('receiveRequest')->assertForbidden();
        $this->assertSame('submitted', $ho->fresh()->status);
    }

    public static function processingMenuRoles(): array
    {
        return [
            'HO' => [['ga.process'], false, PurchasingOfficer::class, ScmProcurement::class],
            'Project' => [['scm.process-apca'], false, ScmProcurement::class, PurchasingOfficer::class],
            'Superuser' => [[], true, PurchasingOfficer::class, ScmProcurement::class],
        ];
    }

    #[DataProvider('processingMenuRoles')]
    public function test_sidebar_shows_the_processing_menu_allowed_by_the_user_role(array $permissions, bool $admin, string $visibleMenu, string $otherMenu): void
    {
        $user = $admin ? User::factory()->create(['is_admin' => true]) : $this->actor($permissions);
        $response = $this->actingAs($user)->get($visibleMenu::getUrl())->assertOk()
            ->assertSee('href="'.$visibleMenu::getUrl().'"', false);
        if ($admin) {
            $response->assertSee('href="'.$otherMenu::getUrl().'"', false);
        } else {
            $response->assertDontSee('href="'.$otherMenu::getUrl().'"', false);
        }
    }
}
