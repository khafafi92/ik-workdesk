<?php

namespace Tests\Feature\Scm;

use App\Filament\Pages\PurchaseRequests;
use App\Filament\Pages\ScmApprovals;
use App\Filament\Pages\ScmProcurement;
use App\Filament\Pages\SignatureProfile;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Scm\ApprovalRoute;
use App\Models\Scm\Audit;
use App\Models\Scm\Procurement;
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
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class HoApprovalsTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private User $manager;

    private User $cbo;

    private User $cbo3;

    private User $scm;

    private Department $department;

    private Workflow $flow;

    private Approvals $approvals;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('scm');
        $this->seed(ScmSeeder::class);
        $this->department = Department::create(['code' => 'HO-TEST', 'name' => 'Departemen HO uji', 'is_active' => true]);
        $this->requester = $this->actor('DEMO Pemohon', ['scm.request']);
        $this->manager = $this->actor('DEMO Atasan', ['scm.request']);
        $this->cbo = $this->actor('DEMO CBO');
        $this->cbo3 = $this->actor('DEMO CBO 3');
        $this->scm = $this->actor('DEMO SCM', ['scm.process', 'scm.configure']);
        $this->flow = app(Workflow::class);
        $this->approvals = app(Approvals::class);
        foreach (['APCA', 'KPMOG'] as $company) {
            $this->approvals->saveRoute($this->scm, ['company' => $company, 'department_id' => $this->department->id,
                'acknowledger_id' => $this->manager->id, 'cbo_id' => $this->cbo->id, 'cbo3_id' => $this->cbo3->id, 'version' => 0]);
        }
        foreach ([$this->requester, $this->manager, $this->cbo, $this->cbo3] as $actor) {
            $this->upload($actor);
        }
    }

    private function actor(string $name, array $permissions = []): User
    {
        $actor = User::factory()->create(['name' => $name]);
        Employee::create(['user_id' => $actor->id, 'department_id' => $this->department->id, 'name' => $name, 'is_active' => true]);
        $actor->directPermissions()->attach(Permission::whereIn('code', $permissions)->pluck('id'));

        return $actor->fresh();
    }

    private function upload(User $actor): void
    {
        app(Signatures::class)->upload($actor, UploadedFile::fake()->createWithContent('DEMO-BUKAN-TTD.png', file_get_contents(database_path('fixtures/scm-demo-signature.png'))));
    }

    private function data(string $amount = '5000000', string $company = 'APCA', string $kind = 'PR'): array
    {
        return ['company' => $company, 'kind' => $kind, 'date' => today()->toDateString(), 'department_id' => $this->department->id,
            'estimated_total' => $amount, 'items' => [['name' => 'DEMO kebutuhan HO', 'specification' => 'Spesifikasi fiktif', 'quantity' => '1', 'unit' => 'unit']]];
    }

    private function pending(string $amount = '5000000', string $company = 'APCA', string $kind = 'PR', ?User $requester = null): Procurement
    {
        $requester ??= $this->requester;
        $r = $this->flow->saveDraft($requester, $this->data($amount, $company, $kind));
        $r = app(Signatures::class)->sign($requester, $r->id, $r->version);

        return $this->flow->submit($requester, $r->id, $r->version);
    }

    private function decide(Procurement $r, string $decision = 'sign'): Procurement
    {
        $step = $this->approvals->next($r);

        return $this->approvals->decide($step->user, $r->id, $r->version, $step->id, $decision);
    }

    private function rejects(callable $action): void
    {
        try {
            $action();
            $this->fail('Keputusan tidak sah harus ditolak.');
        } catch (ValidationException|HttpException $exception) {
            $this->assertTrue($exception instanceof ValidationException || $exception->getStatusCode() === 403);
        }
    }

    public static function routing(): array
    {
        $cases = [];
        foreach (['APCA', 'KPMOG'] as $company) {
            foreach (['PR', 'SR'] as $kind) {
                foreach (['4999999,99' => false, '5000000' => false, '5000000,01' => true] as $amount => $high) {
                    $cases[$company.' '.$kind.' '.$amount] = [(string) $amount, $high, $company, $kind];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('routing')]
    public function test_estimate_selects_the_order_and_scm_receives_only_after_final_approval(string $amount, bool $high, string $company, string $kind): void
    {
        $r = $this->pending($amount, $company, $kind);
        $this->assertSame('approval', $r->status);
        $this->assertNull($r->submitted_at);
        $this->assertFalse(Access::manages($this->scm, $r));
        $this->assertSame(0, Access::scope(Procurement::query(), $this->scm)->count());
        $this->rejects(fn () => $this->flow->receive($this->scm, $r->id, $r->version));
        $this->assertSame($this->manager->id, $this->approvals->next($r)->user_id);
        $r = $this->decide($r);
        $cross = $this->approvals->next($r);
        $this->assertSame('crosschecked', $cross->role);
        $this->assertSame($this->cbo->id, $cross->user_id);
        $this->assertSame($high, $cross->is_required);
        if ($high) {
            $this->rejects(fn () => $this->decide($r, 'skip'));
        }
        $r = $this->decide($r, $high ? 'sign' : 'skip');
        $this->assertSame($high ? $this->cbo3->id : $this->cbo->id, $this->approvals->next($r)->user_id);
        $this->assertSame('approval', $r->status);
        $r = $this->decide($r);
        $this->assertSame('submitted', $r->status);
        $this->assertNotNull($r->submitted_at);
        $this->assertTrue(Access::manages($this->scm, $r));
        $r = $this->flow->receive($this->scm, $r->id, $r->version);
        $this->assertSame('sourcing', $r->status);
        $this->assertSame($company, $r->company);
        $this->assertSame(3, Audit::where('request_id', $r->id)->where('action', 'keputusan_persetujuan_ho')->count());
    }

    public function test_manager_request_skips_acknowledgment_and_low_value_cbo_may_sign_both_positions(): void
    {
        $r = $this->pending(requester: $this->manager);
        $ack = $r->approvals()->where('role', 'acknowledged')->sole();
        $this->assertSame('skipped', $ack->status);
        $this->assertSame('crosschecked', $this->approvals->next($r)->role);
        $r = $this->decide($r);
        $this->assertSame('approved', $this->approvals->next($r)->role);
        $this->assertSame($this->cbo->id, $this->approvals->next($r)->user_id);
        $r = $this->decide($r);
        $this->assertSame('submitted', $r->status);
        $this->assertSame(2, $r->approvals()->where('user_id', $this->cbo->id)->where('status', 'signed')->count());
    }

    public function test_only_the_next_assigned_person_can_decide_and_duplicate_or_stale_actions_fail(): void
    {
        $r = $this->pending('6000000');
        $ack = $this->approvals->next($r);
        $cross = $r->approvals()->where('role', 'crosschecked')->sole();
        $this->rejects(fn () => $this->approvals->decide($this->cbo, $r->id, $r->version, $cross->id, 'sign'));
        $this->rejects(fn () => $this->approvals->decide($this->requester, $r->id, $r->version, $ack->id, 'sign'));
        $this->rejects(fn () => $this->decide($r, 'skip'));
        $oldVersion = $r->version;
        $r = $this->decide($r);
        $this->rejects(fn () => $this->approvals->decide($this->manager, $r->id, $oldVersion, $ack->id, 'sign'));
        $this->assertSame(1, $r->approvals()->where('role', 'acknowledged')->where('status', 'signed')->count());
        $this->rejects(fn () => $this->flow->saveDraft($this->requester, $this->data(), $r->id, $r->version));
        $this->cbo->employee->update(['is_active' => false]);
        $this->rejects(fn () => $this->approvals->decide($this->cbo->fresh(), $r->id, $r->version, $cross->id, 'sign'));
    }

    public function test_revision_keeps_old_signatures_and_new_estimate_starts_a_new_approval_round(): void
    {
        $r = $this->pending();
        $r = $this->decide($r);
        $step = $this->approvals->next($r);
        $this->rejects(fn () => $this->approvals->decide($this->cbo, $r->id, $r->version, $step->id, 'return', 'singkat'));
        $r = $this->approvals->decide($this->cbo, $r->id, $r->version, $step->id, 'return', 'Perbaiki spesifikasi dan estimasi kebutuhan.');
        $this->assertSame('draft', $r->status);
        $this->assertNull($r->prepared_signed_at);
        $this->assertSame(1, $r->approvals()->where('role', 'acknowledged')->where('status', 'signed')->count());
        $this->assertSame(1, $r->approvals()->where('status', 'cancelled')->count());
        $r = $this->flow->saveDraft($this->requester, $this->data('6000000'), $r->id, $r->version);
        $r = app(Signatures::class)->sign($this->requester, $r->id, $r->version);
        $r = $this->flow->submit($this->requester, $r->id, $r->version);
        $this->assertSame(2, $r->approval_round);
        $this->assertSame(8, $r->approvals()->count());
        $this->assertTrue($r->approvals()->where('round', 2)->where('role', 'crosschecked')->sole()->is_required);
        $this->assertSame($this->cbo3->id, $r->approvals()->where('round', 2)->where('role', 'approved')->sole()->user_id);
    }

    public function test_routing_and_profile_changes_do_not_reassign_or_replace_an_existing_signature(): void
    {
        $r = $this->pending('6000000');
        $r = $this->decide($r);
        $signed = $r->approvals()->where('role', 'acknowledged')->sole();
        $this->upload($this->manager);
        $route = $this->approvals->route('APCA', $this->department->id);
        $this->approvals->saveRoute($this->scm, [...$route->getAttributes(), 'cbo_id' => $this->manager->id]);
        $this->assertSame($this->cbo->id, $this->approvals->next($r)->user_id);
        $this->assertSame($signed->signature_id, $signed->fresh()->signature_id);
        $this->assertNotSame(app(Signatures::class)->current($this->manager)->id, $signed->signature_id);
        $this->rejects(fn () => $this->approvals->saveRoute($this->scm, $route->getAttributes()));
        $this->rejects(fn () => $this->approvals->saveRoute($this->requester, $route->getAttributes()));
    }

    public function test_assigned_approver_without_request_permission_can_use_profile_and_only_assigned_requests(): void
    {
        $r = $this->pending();
        $this->assertFalse(Access::has($this->cbo, 'scm.request'));
        Livewire::actingAs($this->cbo)->test(SignatureProfile::class)->assertOk();
        Livewire::actingAs($this->cbo)->test(ScmApprovals::class)->call('open', $r->id)->assertHasNoErrors()
            ->assertSee('Requested by')->assertSee('Menunggu Acknowledged by')->assertDontSee('Giliran Anda:');
        $this->actingAs($this->cbo)->get(route('scm.signature-prepared', $r))->assertOk();
        $this->get(PurchaseRequests::getUrl(['permintaan' => $r->id]))->assertForbidden();
        $unassigned = $this->actor('DEMO Tidak ditugaskan');
        $this->actingAs($unassigned)->get(ScmApprovals::getUrl(['permintaan' => $r->id]))->assertForbidden();
        $this->get(route('scm.signature-prepared', $r))->assertForbidden();
        $r = $this->decide($r);
        $step = $this->approvals->next($r);
        Livewire::actingAs($this->cbo)->test(ScmApprovals::class)->call('open', $r->id)
            ->assertSee('Giliran Anda: Crosschecked by')->assertSee('Lewati Crosschecked by')
            ->call('decide', $step->id, 'skip')->assertHasNoErrors()->assertSee('Giliran Anda: Approved by');
        $r->refresh();
        $step = $this->approvals->next($r);
        Livewire::actingAs($this->cbo)->test(ScmApprovals::class)->call('open', $r->id)
            ->call('decide', $step->id, 'sign')->assertHasNoErrors()->assertDontSee('Giliran Anda:')->assertSee('Menunggu SCM');
        $this->assertSame('submitted', $r->fresh()->status);
    }

    public function test_system_admin_can_open_approval_menu_without_receiving_other_peoples_assignments(): void
    {
        $request = $this->pending();
        $admin = $this->actor('DEMO Sysadmin');
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'system-admin'], ['name' => 'System Admin', 'is_active' => true]));

        $this->actingAs($admin)->get(ScmApprovals::getUrl())->assertOk()
            ->assertSee('Persetujuan PR/SR')
            ->assertSee('Belum ada PR/SR yang ditugaskan kepada Anda.');
        $this->assertTrue(ScmApprovals::canAccess());
        $this->assertFalse(Access::approves($admin));
        Livewire::actingAs($admin)->test(ScmApprovals::class)->call('open', $request->id)->assertStatus(404);
        $step = $this->approvals->next($request);
        $this->rejects(fn () => $this->approvals->decide($admin, $request->id, $request->version, $step->id, 'sign'));
        $this->assertSame('pending', $step->fresh()->status);

        $admin->employee->update(['is_active' => false]);
        $this->actingAs($admin->fresh())->get(ScmApprovals::getUrl())->assertForbidden();
    }

    public function test_legacy_admin_can_open_approval_menu_but_inactive_admin_role_cannot(): void
    {
        $admin = $this->actor('DEMO Admin');
        $admin->update(['is_admin' => true]);
        $this->actingAs($admin)->get(ScmApprovals::getUrl())->assertOk();

        $unassigned = $this->actor('DEMO Role nonaktif');
        $role = Role::firstOrCreate(['code' => 'system-admin'], ['name' => 'System Admin', 'is_active' => true]);
        $role->update(['is_active' => false]);
        $unassigned->roles()->attach($role);
        $this->actingAs($unassigned)->get(ScmApprovals::getUrl())->assertForbidden();
    }

    public function test_optional_estimate_is_validated_when_provided_and_legacy_submitted_requests_remain_receivable(): void
    {
        foreach (['0', '-1', '5.000.000', ['5000000']] as $amount) {
            $data = $this->data();
            $data['estimated_total'] = $amount;
            $this->rejects(fn () => $this->flow->saveDraft($this->requester, $data));
        }
        ApprovalRoute::query()->delete();
        $this->rejects(fn () => $this->pending());
        $this->assertSame(0, Procurement::where('status', 'approval')->count());
        $r = Procurement::sole();
        $r->update(['status' => 'submitted', 'approval_scope' => null, 'estimated_total_minor' => null]);
        $r = $this->flow->receive($this->scm, $r->id, $r->version);
        $this->assertSame('sourcing', $r->status);
    }

    public static function routingChoices(): array
    {
        $cases = [];
        foreach (['APCA', 'KPMOG'] as $company) {
            foreach (['PR', 'SR'] as $kind) {
                foreach ([false, true] as $high) {
                    foreach ([null, $high ? '1000' : '6000000'] as $amount) {
                        $cases[$company.' '.$kind.' '.($high ? 'checked' : 'unchecked').' '.($amount ?? 'no estimate')] = [$high, $amount, $company, $kind];
                    }
                }
            }
        }

        return $cases;
    }

    #[DataProvider('routingChoices')]
    public function test_explicit_choice_routes_pr_and_sr_without_requiring_an_estimate(bool $high, ?string $amount, string $company, string $kind): void
    {
        $data = $this->data('1000', $company, $kind);
        $data['requires_cbo3'] = $high;
        $data['estimated_total'] = $amount;
        $r = $this->flow->saveDraft($this->requester, $data);
        $this->assertSame($high, $r->requires_cbo3);
        if ($amount === null) {
            $this->assertNull($r->estimated_total_minor);
        }
        $r = app(Signatures::class)->sign($this->requester, $r->id, $r->version);
        $r = $this->flow->submit($this->requester, $r->id, $r->version);
        $this->assertSame($high ? $this->cbo3->id : $this->cbo->id, $r->approvals()->where('role', 'approved')->sole()->user_id);
        $this->assertSame($high, $r->approvals()->where('role', 'crosschecked')->sole()->is_required);
        $audit = Audit::where('request_id', $r->id)->where('action', 'mulai_persetujuan_ho')->sole();
        $this->assertSame($high, $audit->changes['requires_cbo3']);
        $r = $this->decide($r);
        $r = $this->decide($r, $high ? 'sign' : 'skip');
        $this->assertSame($high ? $this->cbo3->id : $this->cbo->id, $this->approvals->next($r)->user_id);
        $this->assertSame('submitted', $this->decide($r)->status);
        $this->rejects(fn () => $this->flow->saveDraft($this->requester, [...$data, 'requires_cbo3' => ! $high], $r->id, $r->version));
    }

    public function test_request_ui_defaults_to_cbo_and_choice_changes_require_signing_again(): void
    {
        $data = $this->data();
        unset($data['estimated_total']);
        $data['requires_cbo3'] = true;
        $page = Livewire::actingAs($this->requester)->test(PurchaseRequests::class)
            ->call('newDraft', 'APCA')->assertSet('draft.requires_cbo3', false)
            ->assertSee('Pengajuan di atas Rp5 juta')->assertDontSee('Estimasi total HO')
            ->set('draft', $data)->call('saveDraft')->assertHasNoErrors()
            ->call('signPrepared')->assertHasNoErrors();
        $r = Procurement::sole();
        $this->assertTrue($r->requires_cbo3);
        $this->assertNull($r->estimated_total_minor);
        $this->assertNotNull($r->prepared_signed_at);
        $page->call('editDraft')->assertSet('draft.requires_cbo3', true)
            ->set('draft.requires_cbo3', false)->call('saveDraft')->assertHasNoErrors();
        $this->assertNull($r->fresh()->prepared_signed_at);
        $this->assertFalse($r->fresh()->requires_cbo3);
        $page->call('submitRequest')->assertHasErrors('scm')
            ->call('signPrepared')->assertHasNoErrors()
            ->set('draft.requires_cbo3', true)->call('submitRequest')->assertHasNoErrors();
        $this->assertSame($this->cbo->id, $r->fresh()->approvals()->where('role', 'approved')->sole()->user_id);
    }

    public function test_missing_cbo3_does_not_silently_downgrade_a_checked_request(): void
    {
        $this->approvals->route('APCA', $this->department->id)->update(['cbo3_id' => null]);
        $data = $this->data();
        unset($data['estimated_total']);
        $data['requires_cbo3'] = true;
        $r = $this->flow->saveDraft($this->requester, $data);
        $r = app(Signatures::class)->sign($this->requester, $r->id, $r->version);
        $this->rejects(fn () => $this->flow->submit($this->requester, $r->id, $r->version));
        $this->assertSame('draft', $r->fresh()->status);
        $this->assertSame(0, $r->approvals()->count());
        $data['requires_cbo3'] = false;
        $r = $this->flow->saveDraft($this->requester, $data, $r->id, $r->version);
        $r = app(Signatures::class)->sign($this->requester, $r->id, $r->version);
        $r = $this->flow->submit($this->requester, $r->id, $r->version);
        $this->assertSame($this->cbo->id, $r->approvals()->where('role', 'approved')->sole()->user_id);
    }

    public function test_missing_cbo3_allows_low_value_requests_and_holds_high_value_requests(): void
    {
        $route = $this->approvals->route('APCA', $this->department->id);
        Livewire::actingAs($this->scm)->test(ScmProcurement::class)->call('switchSection', 'settings')
            ->call('editApprovalRoute', $route->id)->set('approvalRoute.cbo3_id', '')
            ->call('saveApprovalRoute')->assertHasNoErrors()->assertSee('belum ditetapkan');
        $this->assertNull($route->fresh()->cbo3_id);
        $low = $this->pending();
        $low = $this->decide($low);
        $low = $this->decide($low, 'skip');
        $low = $this->decide($low);
        $this->assertSame('submitted', $low->status);
        $this->rejects(fn () => $this->pending('5000000,01'));
        $high = Procurement::latest('id')->first();
        $this->assertSame('draft', $high->status);
        $this->assertNotNull($high->prepared_signed_at);
        $this->assertSame(0, $high->approvals()->count());
        $this->approvals->saveRoute($this->scm, [...$route->fresh()->getAttributes(), 'cbo3_id' => $this->cbo3->id]);
        $high = $this->flow->submit($this->requester, $high->id, $high->version);
        $this->assertSame('approval', $high->status);
        $this->assertSame($this->cbo3->id, $high->approvals()->where('role', 'approved')->sole()->user_id);
    }

    public function test_signing_requires_an_available_profile_image_and_keeps_the_step_pending_on_failure(): void
    {
        $r = $this->pending('6000000');
        $r = $this->decide($r);
        $step = $this->approvals->next($r);
        Storage::disk('scm')->delete(app(Signatures::class)->current($this->cbo)->path);
        $this->rejects(fn () => $this->decide($r));
        $this->assertSame('pending', $step->fresh()->status);
        $this->assertSame('approval', $r->fresh()->status);
        $this->upload($this->cbo);
        $r = $this->decide($r);
        $this->assertSame('approved', $this->approvals->next($r)->role);
        $signed = $step->fresh();
        $this->actingAs($this->requester)->get(route('scm.signature-approval', $signed))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $unassigned = $this->actor('DEMO Tanpa penugasan');
        $this->actingAs($unassigned)->get(route('scm.signature-approval', $signed))->assertForbidden();
    }
}
