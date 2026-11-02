<?php

namespace Tests\Feature\Scm;

use App\Filament\Pages\PurchaseRequests;
use App\Filament\Pages\ScmRequestNumbers;
use App\Filament\Pages\ServiceRequests;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Scm\ApprovalPolicy;
use App\Models\Scm\Audit;
use App\Models\Scm\Procurement;
use App\Models\Scm\ProjectNumbering;
use App\Models\Scm\RequestNumber;
use App\Models\User;
use App\Services\Scm\Access;
use App\Services\Scm\Approvals;
use App\Services\Scm\Signatures;
use App\Services\Scm\Workflow;
use Database\Seeders\ScmSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DelegatedPurchaseRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $requester;

    private User $other;

    private Department $home;

    private Department $department;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('scm');
        $this->seed(ScmSeeder::class);
        $this->home = Department::create(['code' => 'OWNER-PR', 'name' => 'Departemen pemilik']);
        $this->department = Department::create(['code' => 'TARGET-PR', 'name' => 'Departemen pemohon']);
        $this->owner = $this->person('Pemilik sistem', $this->home, true);
        $this->requester = $this->person('Pemohon terpilih', $this->department);
        $this->other = $this->person('Pemohon lain', $this->department);
        $this->actingAs($this->owner);
    }

    private function person(string $name, Department $department, bool $systemAdmin = false, bool $mayRequest = true): User
    {
        $user = User::factory()->create(['name' => $name, 'is_admin' => $systemAdmin]);
        Employee::create(['user_id' => $user->id, 'name' => $name, 'department_id' => $department->id, 'is_active' => true]);
        if ($mayRequest) {
            $user->directPermissions()->attach(Permission::where('code', 'scm.request')->sole()->id);
        }

        return $user;
    }

    private function data(array $extra = []): array
    {
        return array_replace(['kind' => 'PR', 'company' => 'KPMOG', 'need_type' => 'PROJECT',
            'project_numbering_id' => ProjectNumbering::where('code', 'COMP-19E001')->sole()->id,
            'requester_id' => $this->requester->id, 'department_id' => $this->department->id,
            'date' => today()->toDateString(), 'title' => 'PR delegasi terpilih', 'reason' => 'Kebutuhan departemen pemohon',
            'items' => [['name' => 'Valve', 'specification' => 'Spesifikasi uji', 'quantity' => '1', 'unit' => 'unit']]], $extra);
    }

    private function policy(): User
    {
        $approver = $this->person('Approver Project', $this->department);
        ApprovalPolicy::create(['company' => 'KPMOG', 'department_id' => $this->department->id, 'need_type' => 'PROJECT', 'request_kind' => 'ALL',
            'acknowledger_id' => $this->requester->id, 'approver_id' => $approver->id, 'use_amount_escalation' => false, 'crosscheck_mode' => 'optional']);

        return $approver;
    }

    private function signature(User $user): void
    {
        app(Signatures::class)->upload($user, UploadedFile::fake()->createWithContent('signature.png', file_get_contents(database_path('fixtures/scm-demo-signature.png'))));
    }

    public static function needTypes(): array
    {
        return [['HO'], ['PROJECT']];
    }

    #[DataProvider('needTypes')]
    public function test_owner_can_create_pr_for_another_department_without_changing_number_issuer(string $type): void
    {
        $request = app(Workflow::class)->saveDraft($this->owner, $this->data(['need_type' => $type]));
        $this->assertSame($this->requester->id, $request->requester_id);
        $this->assertSame($this->department->id, $request->department_id);
        $this->assertSame($this->owner->id, $request->numberReservation->requester_id);
        $audit = Audit::where('request_id', $request->id)->where('action', 'simpan_draft')->sole();
        $this->assertSame($this->owner->id, $audit->actor_id);
        $this->assertSame($this->requester->id, (int) $audit->changes['header']['requester_id']);
        $this->assertTrue(Access::views($this->owner, $request));
        $this->assertTrue(Access::views($this->requester, $request));
        $this->assertFalse(Access::views($this->other, $request));
        Livewire::actingAs($this->requester)->test(PurchaseRequests::class)->assertSee($request->number)->call('open', $request->id)->assertSee('Permintaan dibuat oleh Pemilik sistem untuk pemohon Pemohon terpilih.');
        Livewire::actingAs($this->owner)->test(PurchaseRequests::class)->assertSee($request->number)->call('open', $request->id)->assertSee('Edit draft')
            ->assertSeeHtml('wire:click="signPrepared"')->assertSee('Tanda tangani sebagai Pemilik sistem');
    }

    public function test_superuser_form_offers_all_requesters_and_departments_and_previews_the_selected_requester_approval(): void
    {
        $approver = $this->policy();
        $inactive = $this->person('Tidak aktif', $this->department);
        $inactive->employee->update(['is_active' => false]);
        $noPermission = $this->person('Tanpa izin PR', $this->department, mayRequest: false);
        Livewire::test(PurchaseRequests::class)->set('newRequestNeedType', 'PROJECT')
            ->set('newProjectNumberingId', (string) ProjectNumbering::where('code', 'COMP-19E001')->sole()->id)->call('newDraft', 'KPMOG')
            ->assertSeeHtml('id="scm-draft-requester_id"')
            ->assertViewHas('requesterOptions', fn ($options) => isset($options[$inactive->id], $options[$noPermission->id], $options[$this->requester->id]))
            ->set('draft.requester_id', (string) $this->requester->id)->assertSet('draft.department_id', $this->department->id)
            ->assertViewHas('departments', fn ($departments) => $departments->has($this->department->id) && $departments->has($this->home->id))
            ->assertViewHas('routingPlan', fn ($plan) => $plan['manager_request'] && $plan['approver']->id === $approver->id)
            ->assertSeeHtml('wire:click="signPrepared"')->set('draft.items.0.name', 'Valve')->set('draft.items.0.specification', 'Spesifikasi')
            ->set('draft.items.0.unit', 'unit')->call('saveDraft')->assertHasNoErrors();
        $this->assertSame($this->requester->id, Procurement::sole()->requester_id);
    }

    public function test_admin_and_configuration_permissions_do_not_allow_impersonation(): void
    {
        $admin = $this->person('Administrator biasa', $this->home);
        $role = Role::firstOrCreate(['code' => 'administrator'], ['name' => 'Administrator', 'is_active' => true]);
        $admin->roles()->attach($role);
        $admin->directPermissions()->attach(Permission::where('code', 'scm.configure')->sole()->id);
        $this->assertFalse(Access::canChooseRequester($admin->fresh(), 'PR'));
        Livewire::actingAs($admin)->test(PurchaseRequests::class)->call('newDraft', 'KPMOG')
            ->assertDontSeeHtml('id="scm-draft-requester_id"')->set('draft.requester_id', $this->requester->id)->assertForbidden();
        $count = RequestNumber::count();
        try {
            app(Workflow::class)->saveDraft($admin, $this->data());
            $this->fail('Ordinary administrator must not delegate.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        $this->assertSame($count, RequestNumber::count());
        $this->assertSame(0, Procurement::count());
    }

    public function test_ordinary_requester_cannot_submit_a_forged_requester_id(): void
    {
        $this->expectException(HttpException::class);
        app(Workflow::class)->saveDraft($this->other, $this->data());
    }

    public function test_sysadmin_role_is_accepted_for_pr_and_sr_without_explicit_permissions(): void
    {
        $role = Role::firstOrCreate(['code' => 'system-admin'], ['name' => 'Sys Administrator', 'is_active' => true]);
        $this->other->roles()->attach($role);
        $this->other->directPermissions()->detach();
        $this->assertTrue(Access::canChooseRequester($this->other->fresh(), 'PR'));
        $this->assertTrue(Access::canChooseRequester($this->other->fresh(), 'SR'));
        $request = app(Workflow::class)->saveDraft($this->other->fresh(), $this->data());
        $this->assertSame($this->requester->id, $request->requester_id);
        Livewire::actingAs($this->owner)->test(ServiceRequests::class)->call('newDraft', 'KPMOG')->assertSeeHtml('id="scm-draft-requester_id"');
        $sr = app(Workflow::class)->saveDraft($this->owner, $this->data(['kind' => 'SR']));
        $this->assertSame($this->requester->id, $sr->requester_id);
    }

    public function test_owner_can_assign_a_department_outside_the_requester_access(): void
    {
        $request = app(Workflow::class)->saveDraft($this->owner, $this->data(['department_id' => $this->home->id]));
        $this->assertSame($this->home->id, $request->department_id);
    }

    public function test_owner_can_prepare_for_inactive_requester_but_inactive_actor_has_no_override(): void
    {
        $this->requester->employee->update(['is_active' => false]);
        $request = app(Workflow::class)->saveDraft($this->owner, $this->data());
        $this->assertSame($this->requester->id, $request->requester_id);
        $this->owner->employee->update(['is_active' => false]);
        $this->assertFalse($this->owner->fresh()->isSystemAdministrator());
        $this->expectException(HttpException::class);
        app(Workflow::class)->saveDraft($this->owner->fresh(), $this->data());
    }

    public function test_superuser_signs_submits_and_approves_with_own_signature_and_original_assignments(): void
    {
        $approver = $this->policy();
        $request = app(Workflow::class)->saveDraft($this->owner, $this->data());
        $this->signature($this->owner);
        $this->signature($this->requester);
        $request = app(Signatures::class)->sign($this->owner, $request->id, $request->version);
        $this->assertSame($this->owner->name, $request->prepared_signed_name);
        $this->assertSame(app(Signatures::class)->current($this->owner)->id, $request->prepared_signature_id);
        $request = app(Workflow::class)->submit($this->owner, $request->id, $request->version);
        $this->assertSame('approval', $request->status);
        $this->assertSame($this->requester->id, $request->approvals->firstWhere('role', 'requested')->user_id);
        $this->assertSame($approver->id, $request->approvals->firstWhere('role', 'approved')->user_id);
        $service = app(Approvals::class);
        $step = $service->next($request);
        $this->assertSame(1, $service->pendingCount($this->owner));
        $request = $service->decide($this->owner, $request->id, $request->version, $step->id, 'sign');
        $this->assertSame($approver->id, $step->fresh()->user_id);
        $this->assertSame($this->owner->name, $step->fresh()->signed_name);
        $audit = Audit::where('request_id', $request->id)->latest('id')->first();
        $this->assertSame($this->owner->id, $audit->actor_id);
        $this->assertTrue($audit->changes['superuser_override']);
        $this->assertSame($approver->id, $audit->changes['assigned_user_id']);
    }

    public function test_owner_can_attach_and_reassign_draft_but_reassignment_clears_signature(): void
    {
        $request = app(Workflow::class)->saveDraft($this->owner, $this->data());
        $this->signature($this->requester);
        $request = app(Signatures::class)->sign($this->requester, $request->id, $request->version);
        $request = app(Workflow::class)->attach($this->owner, $request->id, $request->version, 'request', 0,
            UploadedFile::fake()->createWithContent('lampiran.pdf', "%PDF-1.4\n%%EOF"));
        $this->assertNull($request->prepared_signed_at);
        $this->assertSame($this->owner->id, $request->attachments->sole()->uploaded_by);
        $request = app(Signatures::class)->sign($this->requester, $request->id, $request->version);
        $number = $request->number;
        $request = app(Workflow::class)->saveDraft($this->owner, $this->data(['requester_id' => $this->other->id]), $request->id, $request->version);
        $this->assertSame($number, $request->number);
        $this->assertNull($request->prepared_signed_at);
        $this->assertSame($this->other->id, $request->requester_id);
        $this->assertFalse(Access::views($this->requester, $request));
        $this->assertTrue(Access::views($this->other, $request));
        $this->assertTrue(Access::views($this->owner, $request));
    }

    public function test_revoking_sysadmin_removes_delegated_access_including_number_register(): void
    {
        $request = app(Workflow::class)->saveDraft($this->owner, $this->data());
        $this->owner->update(['is_admin' => false]);
        $formerOwner = $this->owner->fresh();
        $this->assertFalse(Access::views($formerOwner, $request->fresh()));
        Livewire::actingAs($formerOwner)->test(PurchaseRequests::class)->assertDontSee($request->number)->call('open', $request->id)->assertForbidden();
        Livewire::actingAs($formerOwner)->test(ScmRequestNumbers::class)->assertDontSee('PR delegasi terpilih');
        $this->assertTrue(Access::views($this->requester, $request));
    }

    public function test_owner_can_edit_someone_elses_unrelated_draft_and_view_its_number(): void
    {
        $request = app(Workflow::class)->saveDraft($this->other, $this->data(['requester_id' => $this->other->id]));
        $this->assertTrue(Access::views($this->owner, $request));
        Livewire::test(PurchaseRequests::class)->assertSee($request->number)->call('open', $request->id)->call('editDraft')->assertSet('editing', true);
        Livewire::test(ScmRequestNumbers::class)->assertSee($request->number);
    }
}
