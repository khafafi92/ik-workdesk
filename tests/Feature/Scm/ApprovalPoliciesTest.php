<?php

namespace Tests\Feature\Scm;

use App\Filament\Pages\PurchaseRequests;
use App\Filament\Pages\ScmProcurement;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Scm\ApprovalPolicy;
use App\Models\Scm\ApprovalRoute;
use App\Models\Scm\Procurement;
use App\Models\Scm\UserSignature;
use App\Models\User;
use App\Services\Scm\Access;
use App\Services\Scm\ApprovalPolicies;
use App\Services\Scm\Approvals;
use App\Services\Scm\Signatures;
use App\Services\Scm\Workflow;
use Database\Seeders\ScmSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ApprovalPoliciesTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    private User $scm;

    private User $staff;

    private User $manager;

    private User $cbo;

    private User $coo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', app()->environment());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('scm');
        $this->seed(ScmSeeder::class);
        $this->department = Department::create(['code' => 'POLICY-TEST', 'name' => 'Divisi Project']);
        $this->scm = $this->actor('Pengelola master', 'staff', ['scm.configure']);
        $this->staff = $this->actor('Merista', 'staff', ['scm.request']);
        $this->manager = $this->actor('Manager Project', 'manager');
        $this->cbo = $this->actor('CBO', 'cbo');
        $this->coo = $this->actor('COO II', 'coo-2');
    }

    private function actor(string $name, string $level, array $permissions = []): User
    {
        $user = User::factory()->create(['name' => $name, 'is_admin' => false]);
        Employee::create(['user_id' => $user->id, 'department_id' => $this->department->id, 'name' => $name, 'position_level' => $level, 'is_active' => true]);
        $user->directPermissions()->attach(Permission::whereIn('code', $permissions)->pluck('id'));
        $path = 'signatures/policy-'.$user->id.'.png';
        Storage::disk('scm')->put($path, file_get_contents(database_path('fixtures/scm-demo-signature.png')));
        UserSignature::create(['user_id' => $user->id, 'path' => $path, 'mime' => 'image/png']);

        return $user->fresh();
    }

    private function data(array $extra = []): array
    {
        return array_replace(ApprovalPolicies::defaults(), ['department_id' => $this->department->id, 'need_type' => 'PROJECT',
            'acknowledger_id' => $this->manager->id, 'approver_id' => $this->coo->id, 'use_amount_escalation' => false, 'crosscheck_mode' => 'always'], $extra);
    }

    private function pending(string $type = 'PROJECT', string $kind = 'PR', bool $high = false): Procurement
    {
        $flow = app(Workflow::class);
        $request = $flow->saveDraft($this->staff, ['company' => 'APCA', 'kind' => $kind, 'need_type' => $type,
            'department_id' => $this->department->id, 'date' => today()->toDateString(), 'requires_cbo3' => $high,
            'items' => [['name' => 'Kebutuhan pengujian', 'specification' => 'Spesifikasi uji', 'quantity' => '1', 'unit' => 'unit']]]);
        $request = app(Signatures::class)->sign($this->staff, $request->id, $request->version);

        return $flow->submit($this->staff, $request->id, $request->version);
    }

    public static function projectCases(): array
    {
        return [['PR', false], ['PR', true], ['SR', false], ['SR', true]];
    }

    #[DataProvider('projectCases')]
    public function test_project_staff_goes_to_configured_manager_and_coo_ii_without_cbo_or_crosscheck(string $kind, bool $high): void
    {
        app(ApprovalPolicies::class)->save($this->scm, $this->data());
        $this->assertTrue(Access::approves($this->manager));
        $this->assertFalse(Access::manages($this->manager));
        $request = $this->pending(kind: $kind, high: $high);
        $this->assertSame('PROJECT', $request->need_type);
        $this->assertSame(['requested', 'acknowledged', 'approved'], $request->approvals()->orderBy('position')->pluck('role')->all());
        $this->assertFalse($request->approvals()->where('user_id', $this->cbo->id)->exists());
        $approvals = app(Approvals::class);
        $step = $approvals->next($request);
        $this->assertSame($this->manager->id, $step->user_id);
        $request = $approvals->decide($this->manager, $request->id, $request->version, $step->id, 'sign');
        $step = $approvals->next($request);
        $this->assertSame($this->coo->id, $step->user_id);
        $request = $approvals->decide($this->coo, $request->id, $request->version, $step->id, 'sign');
        $this->assertSame('submitted', $request->status);
        $html = view('filament.pages.scm.partials.request-sheet', ['record' => $request->load(['requester', 'department', 'items', 'approvals.user']), 'sheetEditing' => false])->render();
        $doc = new \DOMDocument;
        @$doc->loadHTML($html);
        $xpath = new \DOMXPath($doc);
        $columns = $xpath->query('//div[contains(concat(" ",normalize-space(@class)," ")," scm-sheet-signature ")]');
        $this->assertSame(4, $columns->length);
        $this->assertSame('Crosschecked by', trim($columns->item(2)->textContent));
        $this->assertSame('COO II', trim($xpath->query('./div', $columns->item(3))->item(2)->textContent));
    }

    public function test_ho_and_project_have_different_masters_without_overwriting_each_other(): void
    {
        $policies = app(ApprovalPolicies::class);
        $policies->save($this->scm, $this->data());
        $policies->save($this->scm, $this->data(['need_type' => 'HO', 'crosschecker_id' => $this->cbo->id, 'approver_id' => $this->cbo->id,
            'use_amount_escalation' => true, 'high_approver_id' => $this->coo->id, 'crosscheck_mode' => 'above_limit']));
        $project = $this->pending();
        $ho = $this->pending('HO', high: true);
        $this->assertSame(2, ApprovalPolicy::count());
        $this->assertFalse($project->approvals()->where('role', 'crosschecked')->exists());
        $this->assertSame($this->cbo->id, $ho->approvals()->where('role', 'crosschecked')->sole()->user_id);
        $this->assertTrue($ho->approvals()->where('role', 'crosschecked')->sole()->is_required);
        $this->assertSame($this->coo->id, $ho->approvals()->where('role', 'approved')->sole()->user_id);
    }

    public static function signerFields(): array
    {
        return [['supervisor_id'], ['acknowledger_id'], ['crosschecker_id'], ['approver_id'], ['high_approver_id']];
    }

    #[DataProvider('signerFields')]
    public function test_cbo_cannot_be_assigned_to_any_project_approval_position(string $field): void
    {
        $this->expectException(ValidationException::class);
        app(ApprovalPolicies::class)->save($this->scm, $this->data([$field => $this->cbo->id, 'use_amount_escalation' => true]));
    }

    public function test_project_does_not_fall_back_to_ho_when_project_master_is_missing(): void
    {
        app(ApprovalPolicies::class)->save($this->scm, $this->data(['need_type' => 'HO', 'approver_id' => $this->cbo->id]));
        try {
            $this->pending();
            $this->fail('Project must require its own master.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Master approval Project', $e->getMessage());
        }
        $request = Procurement::latest('id')->sole();
        $this->assertSame('draft', $request->status);
        $this->assertNotNull($request->prepared_signed_at);
        $this->assertSame(0, $request->approvals()->count());
    }

    public function test_specific_sr_rule_takes_precedence_over_shared_pr_sr_master(): void
    {
        $policies = app(ApprovalPolicies::class);
        $policies->save($this->scm, $this->data());
        $other = $this->actor('COO I', 'coo-1');
        $policies->save($this->scm, $this->data(['request_kind' => 'SR', 'approver_id' => $other->id]));
        $pr = $this->pending();
        $sr = $this->pending(kind: 'SR');
        $this->assertSame($this->coo->id, $pr->approvals()->where('role', 'approved')->sole()->user_id);
        $this->assertSame($other->id, $sr->approvals()->where('role', 'approved')->sole()->user_id);
    }

    public function test_master_changes_only_affect_new_submissions_and_stale_edits_are_rejected(): void
    {
        $policies = app(ApprovalPolicies::class);
        $policy = $policies->save($this->scm, $this->data());
        $first = $this->pending();
        $other = $this->actor('COO I', 'coo-1');
        $policies->save($this->scm, $this->data(['approver_id' => $other->id, 'version' => $policy->version]));
        $second = $this->pending();
        $this->assertSame($this->coo->id, $first->approvals()->where('role', 'approved')->sole()->user_id);
        $this->assertSame($other->id, $second->approvals()->where('role', 'approved')->sole()->user_id);
        $this->expectException(ValidationException::class);
        $policies->save($this->scm, $this->data(['version' => $policy->version]));
    }

    public function test_named_master_acknowledger_takes_precedence_over_organization_mapping(): void
    {
        $other = $this->actor('Atasan organisasi lain', 'manager');
        $this->staff->employee->update(['supervisor_employee_id' => $other->employee->id]);
        app(ApprovalPolicies::class)->save($this->scm, $this->data());
        $request = $this->pending();
        $this->assertSame($this->manager->id, app(Approvals::class)->next($request)->user_id);
        $this->assertFalse($request->approvals()->where('user_id', $other->id)->exists());
    }

    public function test_custom_ho_master_keeps_manager_own_request_acknowledgment_and_optional_crosscheck_blank(): void
    {
        $this->staff->directPermissions()->sync([]);
        $this->staff = $this->manager;
        $this->staff->directPermissions()->attach(Permission::where('code', 'scm.request')->value('id'));
        $this->staff->unsetRelation('directPermissions');
        app(ApprovalPolicies::class)->save($this->scm, $this->data(['need_type' => 'HO', 'crosschecker_id' => $this->cbo->id,
            'approver_id' => $this->cbo->id, 'crosscheck_mode' => 'above_limit']));
        $request = $this->pending('HO');
        $this->assertSame('skipped', $request->approvals()->where('role', 'acknowledged')->sole()->status);
        $this->assertSame('skipped', $request->approvals()->where('role', 'crosschecked')->sole()->status);
        $this->assertSame('approved', app(Approvals::class)->next($request)->role);
        $this->assertSame($this->cbo->id, app(Approvals::class)->next($request)->user_id);
    }

    public function test_changing_a_signer_to_cbo_or_inactive_blocks_new_project_submissions(): void
    {
        app(ApprovalPolicies::class)->save($this->scm, $this->data());
        $this->coo->employee->update(['position_level' => 'cbo']);
        $this->expectException(ValidationException::class);
        $this->pending();
    }

    public function test_master_can_be_saved_and_edited_through_scm_settings(): void
    {
        $page = Livewire::actingAs($this->scm)->test(ScmProcurement::class)->set('section', 'settings');
        $page->set('approvalPolicy', $this->data())->call('saveApprovalPolicy')->assertHasNoErrors();
        $policy = ApprovalPolicy::sole();
        $page->call('editApprovalPolicy', $policy->id)->assertSet('approvalPolicy.need_type', 'PROJECT')
            ->assertSet('approvalPolicy.approver_id', $this->coo->id)
            ->assertSee('Master Approval PR/SR')->assertSet('approvalPolicy.crosschecker_id', null);
        $page->call('copyApprovalPolicy', $policy->id)->assertSet('approvalPolicy.version', 0);
        $this->assertSame(1, ApprovalPolicy::count());
        $this->assertFalse(Access::has($this->coo, 'scm.configure'));
    }

    public function test_requester_cannot_modify_master_and_type_changes_require_signing_again(): void
    {
        try {
            app(ApprovalPolicies::class)->save($this->staff, $this->data());
            $this->fail('Requester cannot configure approval.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        app(ApprovalPolicies::class)->save($this->scm, $this->data());
        $request = app(Workflow::class)->saveDraft($this->staff, ['company' => 'APCA', 'kind' => 'PR', 'need_type' => 'HO',
            'date' => today()->toDateString(), 'department_id' => $this->department->id,
            'items' => [['name' => 'Uji', 'specification' => 'Spek', 'quantity' => '1', 'unit' => 'unit']]]);
        $request = app(Signatures::class)->sign($this->staff, $request->id, $request->version);
        Livewire::actingAs($this->staff)->test(PurchaseRequests::class)->call('open', $request->id)->call('editDraft')
            ->set('draft.need_type', 'PROJECT')->call('saveDraft')->assertHasNoErrors();
        $this->assertSame('PROJECT', $request->fresh()->need_type);
        $this->assertNull($request->fresh()->prepared_signed_at);
    }

    public function test_cbo_crosscheck_cannot_be_skipped_on_ho_when_final_approver_is_coo(): void
    {
        app(ApprovalPolicies::class)->save($this->scm, $this->data(['need_type' => 'HO', 'crosschecker_id' => $this->cbo->id,
            'crosscheck_mode' => 'optional']));
        $request = $this->pending('HO');
        $step = app(Approvals::class)->next($request);
        $request = app(Approvals::class)->decide($this->manager, $request->id, $request->version, $step->id, 'sign');
        $step = app(Approvals::class)->next($request);
        $this->assertSame($this->cbo->id, $step->user_id);
        $this->assertTrue($step->is_required);
        $this->expectException(ValidationException::class);
        app(Approvals::class)->decide($this->cbo, $request->id, $request->version, $step->id, 'skip');
    }

    public function test_ho_master_cannot_omit_cbo_review(): void
    {
        $this->expectException(ValidationException::class);
        app(ApprovalPolicies::class)->save($this->scm, $this->data(['need_type' => 'HO']));
    }

    public function test_project_master_still_validates_an_inactive_final_approver(): void
    {
        app(ApprovalPolicies::class)->save($this->scm, $this->data());
        $this->coo->employee->update(['is_active' => false]);
        $this->expectException(ValidationException::class);
        $this->pending();
    }

    public function test_migration_imports_existing_ho_route_without_changing_existing_requests_or_steps(): void
    {
        $route = app(Approvals::class)->saveRoute($this->scm, ['company' => 'APCA', 'department_id' => $this->department->id,
            'acknowledger_id' => $this->manager->id, 'cbo_id' => $this->cbo->id, 'cbo3_id' => $this->coo->id, 'version' => 0]);
        $request = $this->pending('HO');
        $before = json_encode([DB::table('scm_requests')->get(), DB::table('scm_request_approvals')->get()]);
        $migration = require database_path('migrations/2026_10_27_110000_create_scm_approval_policies.php');
        $migration->down();
        $migration->up();
        $policy = ApprovalPolicy::sole();
        $this->assertFalse($policy->uses_legacy_routing);
        $this->assertSame($route->id, $policy->legacy_route_id);
        $this->assertSame('HO', $policy->need_type);
        $this->assertSame($this->cbo->id, $policy->approver_id);
        $this->assertSame($this->coo->id, $policy->high_approver_id);
        $this->assertSame($before, json_encode([DB::table('scm_requests')->get(), DB::table('scm_request_approvals')->get()]));
        $this->assertSame($this->manager->id, app(Approvals::class)->next($request->fresh())->user_id);
    }

    public function test_custom_master_is_not_overwritten_or_deleted_by_legacy_route_updates(): void
    {
        $route = app(Approvals::class)->saveRoute($this->scm, ['company' => 'APCA', 'department_id' => $this->department->id,
            'acknowledger_id' => $this->manager->id, 'cbo_id' => $this->cbo->id, 'cbo3_id' => $this->coo->id, 'version' => 0]);
        $policy = ApprovalPolicy::sole();
        app(ApprovalPolicies::class)->save($this->scm, $this->data(['need_type' => 'HO', 'approver_id' => $this->cbo->id, 'version' => $policy->version]));
        $other = $this->actor('Manager pengaturan lama', 'manager');
        app(Approvals::class)->saveRoute($this->scm, [...$route->getAttributes(), 'acknowledger_id' => $other->id]);
        $request = $this->pending('HO');
        $this->assertSame($this->manager->id, app(Approvals::class)->next($request)->user_id);
        $route->fresh()->delete();
        $this->assertSame(1, ApprovalPolicy::count());
        $this->assertNull($policy->fresh()->legacy_route_id);
        $this->assertSame(0, ApprovalRoute::count());
    }
}
