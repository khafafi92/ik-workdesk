<?php

namespace Tests\Feature\Scm;

use App\Filament\Pages\ScmApprovals;
use App\Filament\Pages\ScmCbeApprovals;
use App\Filament\Pages\ScmProcurement;
use App\Filament\Pages\SignatureProfile;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Scm\Cbe;
use App\Models\Scm\CbeApprovalPolicy;
use App\Models\Scm\Procurement;
use App\Models\User;
use App\Services\Scm\CbeApprovals;
use App\Services\Scm\Signatures;
use App\Services\Scm\Workflow;
use Database\Seeders\CbeApprovalPolicySeeder;
use Database\Seeders\ScmSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CbeApprovalsTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    private User $scm;

    private User $meyfi;

    private User $dipta;

    private User $lenaldy;

    private User $outsider;

    private CbeApprovals $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Storage::fake('scm');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->seed(ScmSeeder::class);
        $this->department = Department::create(['code' => 'CBE-TEST', 'name' => 'Departemen pengujian CBE', 'is_active' => true]);
        $this->scm = $this->actor('Imam pengujian', ['scm.process', 'scm.configure']);
        $this->meyfi = $this->actor('Meyfi pengujian');
        $this->dipta = $this->actor('Dipta pengujian');
        $this->lenaldy = $this->actor('Lenaldy pengujian');
        $this->outsider = $this->actor('Akun lain pengujian');
        $this->service = app(CbeApprovals::class);
        $this->service->savePolicy($this->scm, $this->policy());
        foreach ([$this->scm, $this->meyfi, $this->dipta, $this->lenaldy] as $user) {
            $this->signature($user);
        }
    }

    private function actor(string $name, array $permissions = []): User
    {
        $user = User::factory()->create(['name' => $name, 'is_admin' => false]);
        Employee::create(['user_id' => $user->id, 'department_id' => $this->department->id, 'name' => $name, 'is_active' => true]);
        $user->directPermissions()->attach(Permission::whereIn('code', $permissions)->pluck('id'));

        return $user->fresh();
    }

    private function signature(User $user): void
    {
        app(Signatures::class)->upload($user, UploadedFile::fake()->createWithContent('DEMO-BUKAN-TTD.png', file_get_contents(database_path('fixtures/scm-demo-signature.png'))));
    }

    private function policy(array $changes = []): array
    {
        return array_replace(CbeApprovals::defaults(), ['acknowledger_id' => $this->meyfi->id, 'crosschecker_id' => $this->dipta->id, 'approver_id' => $this->lenaldy->id], $changes);
    }

    private function cbe(bool $start = true, bool $required = true): Cbe
    {
        $request = Procurement::create(['company' => 'KPMOG', 'number' => 'CBE-PR-TEST', 'kind' => 'PR', 'date' => today(),
            'requester_id' => $this->scm->id, 'department_id' => $this->department->id, 'title' => 'Dokumen pengujian CBE', 'reason' => 'Pengujian', 'status' => 'selected']);
        $cbe = Cbe::create(['request_id' => $request->id, 'number' => 'CBE-TEST', 'date' => today(), 'status' => 'final', 'prepared_by' => $this->scm->id,
            'finalized_at' => now(), 'final_minor' => 0, 'snapshot' => ['approval_required' => $required, 'prepared_by_name' => $this->scm->name,
                'request' => [...$request->toArray(), 'department_name' => $this->department->name], 'items' => [], 'quotes' => []]]);
        if ($start) {
            DB::transaction(fn () => $this->service->start($this->scm, $cbe));
        }

        return $cbe;
    }

    private function rejects(callable $action): void
    {
        try {
            $action();
            $this->fail('Tindakan seharusnya ditolak.');
        } catch (ValidationException|HttpException $exception) {
            $this->assertTrue(true);
        }
    }

    public function test_prepared_uses_scm_login_and_other_signatures_are_not_fabricated(): void
    {
        $cbe = $this->cbe();
        $steps = $cbe->approvals()->get();
        $this->assertCount(4, $steps);
        $this->assertSame(['prepared', 'acknowledged', 'crosschecked', 'approved'], $steps->pluck('role')->all());
        $this->assertSame($this->scm->id, $steps[0]->user_id);
        $this->assertSame('Imam pengujian', $steps[0]->signed_name);
        $this->assertNotNull($steps[0]->acted_at);
        foreach ($steps->slice(1) as $step) {
            $this->assertSame('pending', $step->status);
            $this->assertNull($step->signature_id);
            $this->assertNull($step->acted_at);
        }
        $this->assertFalse($this->service->complete($cbe));
    }

    public function test_signing_is_ordered_authorized_versioned_and_keeps_the_final_snapshot(): void
    {
        $cbe = $this->cbe();
        $snapshot = $cbe->snapshot;
        $this->rejects(fn () => $this->service->sign($this->dipta, $cbe->id, $cbe->request->version));
        $this->rejects(fn () => $this->service->sign($this->outsider, $cbe->id, $cbe->request->version));
        $this->rejects(fn () => $this->service->sign($this->meyfi, $cbe->id, 99));
        foreach ([$this->meyfi, $this->dipta, $this->lenaldy] as $user) {
            $this->service->sign($user, $cbe->id, $cbe->request()->first()->version);
        }
        $this->assertTrue($this->service->complete($cbe));
        $this->assertSame($snapshot, $cbe->fresh()->snapshot);
        $this->assertSame(4, $cbe->approvals()->where('status', 'signed')->count());
        $this->rejects(fn () => $this->service->sign($this->lenaldy, $cbe->id, $cbe->request()->first()->version));
    }

    public function test_master_changes_only_affect_future_documents_and_require_configure_access(): void
    {
        $cbe = $this->cbe();
        $this->rejects(fn () => $this->service->savePolicy($this->outsider, $this->policy(['version' => 1])));
        $this->rejects(fn () => $this->service->savePolicy($this->scm, $this->policy(['version' => 0])));
        $this->rejects(fn () => $this->service->savePolicy($this->scm, $this->policy(['version' => 1, 'crosschecker_id' => $this->meyfi->id])));
        $this->service->savePolicy($this->scm, $this->policy(['version' => 1, 'acknowledger_id' => $this->outsider->id]));
        $this->assertSame($this->meyfi->id, $this->service->next($cbe)->user_id);
    }

    public function test_missing_policy_or_signature_cannot_start_approval(): void
    {
        $cbe = $this->cbe(false);
        CbeApprovalPolicy::query()->delete();
        $this->rejects(fn () => DB::transaction(fn () => $this->service->start($this->scm, $cbe)));
        $this->assertSame(0, $cbe->approvals()->count());
        $this->service->savePolicy($this->scm, $this->policy());
        Storage::disk('scm')->delete(app(Signatures::class)->current($this->scm)->path);
        $this->rejects(fn () => DB::transaction(fn () => $this->service->start($this->scm, $cbe)));
        $this->assertSame(0, $cbe->approvals()->count());
    }

    public function test_signature_images_require_document_access_and_survive_profile_replacement(): void
    {
        $cbe = $this->cbe();
        $step = $cbe->approvals()->first();
        $signatureId = $step->signature_id;
        $this->signature($this->scm);
        $this->assertSame($signatureId, $step->fresh()->signature_id);
        $this->actingAs($this->outsider)->get(route('scm.signature-cbe', $step))->assertForbidden();
        $this->actingAs($this->meyfi)->get(route('scm.signature-cbe', $step))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('scm.signature-cbe', $this->service->next($cbe)))->assertNotFound();
    }

    public function test_assigned_user_can_sign_from_the_combined_approval_page(): void
    {
        $cbe = $this->cbe();
        Livewire::actingAs($this->meyfi)->test(ScmApprovals::class)->call('openCbe', $cbe->id)->assertSee('Tanda tangani sebagai Acknowledged By')
            ->assertSee(SignatureProfile::getUrl())
            ->call('signCbe')->assertHasNoErrors()->assertDispatched('refresh-sidebar')->assertSee('Menunggu Dipta pengujian')
            ->call('switchDocument', 'PRSR')->assertSet('selectedCbe', 0)->assertSee('Persetujuan PR/SR')
            ->call('switchDocument', 'CBE')->assertSee('CBE-TEST');
        $this->assertSame($this->dipta->id, $this->service->next($cbe)->user_id);
        Livewire::actingAs($this->outsider)->test(ScmApprovals::class)->assertForbidden();
    }

    public function test_one_master_panel_switches_between_prsr_and_cbe_without_replacing_existing_rules(): void
    {
        $policy = CbeApprovalPolicy::where('company', 'KPMOG')->sole();
        $page = Livewire::actingAs($this->scm)->test(ScmProcurement::class)->call('switchSection', 'settings')
            ->assertSet('approvalDocument', 'PRSR')->assertSee('Master Approval')->assertSee('Divisi / departemen')->assertDontSee('Tambah aturan CBE')
            ->set('approvalDocument', 'CBE')->assertSee('Tambah aturan CBE')->assertDontSee('Divisi / departemen')
            ->call('editCbeApprovalPolicy', $policy->id)->assertSet('approvalDocument', 'CBE')->assertSet('cbeApprovalPolicy.version', 1)
            ->call('saveCbeApprovalPolicy')->assertHasNoErrors()->assertSet('approvalDocument', 'CBE')
            ->set('approvalDocument', 'PRSR')->assertSee('Divisi / departemen')->assertDontSee('Tambah aturan CBE');
        $this->assertSame(2, $policy->fresh()->version);
        $this->assertSame($this->meyfi->id, $policy->fresh()->acknowledger_id);
        $this->assertSame(1, CbeApprovalPolicy::count());
        $processor = $this->actor('SCM tanpa izin konfigurasi', ['scm.process']);
        Livewire::actingAs($processor)->test(ScmProcurement::class)->set('approvalDocument', 'CBE')->assertForbidden();
    }

    public function test_legacy_cbe_link_redirects_to_the_single_approval_menu_and_preserves_document_selection(): void
    {
        $cbe = $this->cbe();
        $this->assertSame('Persetujuan', ScmApprovals::getNavigationLabel());
        $this->assertFalse(ScmCbeApprovals::shouldRegisterNavigation());
        $this->actingAs($this->meyfi)->get(ScmCbeApprovals::getUrl(['cbe' => $cbe->id]))
            ->assertRedirect(ScmApprovals::getUrl(['dokumen' => 'CBE', 'cbe' => $cbe->id]));
        $this->get(ScmApprovals::getUrl(['dokumen' => 'CBE', 'cbe' => $cbe->id]))->assertOk()->assertSee('CBE-TEST');
        $other = $this->actor('SCM lain', ['scm.process']);
        Livewire::actingAs($other)->test(ScmApprovals::class)->call('openCbe', $cbe->id)->assertNotFound();
    }

    public function test_combined_menu_badge_counts_only_cbe_signatures_that_are_currently_actionable(): void
    {
        $cbe = $this->cbe();
        $this->assertSame(1, $this->service->pendingCount($this->meyfi));
        $this->assertSame(0, $this->service->pendingCount($this->dipta));
        $this->actingAs($this->meyfi);
        $this->assertSame('1', ScmApprovals::getNavigationBadge());
        $this->service->sign($this->meyfi, $cbe->id, $cbe->request->version);
        $this->assertSame(0, $this->service->pendingCount($this->meyfi));
        $this->assertSame(1, $this->service->pendingCount($this->dipta));
        $this->assertNull(ScmApprovals::getNavigationBadge());
    }

    public function test_incomplete_signatures_block_legal_and_prepared_is_not_hardcoded_to_imam(): void
    {
        $this->scm = $this->actor('Petugas SCM pengganti', ['scm.process']);
        $this->signature($this->scm);
        $cbe = $this->cbe();
        $this->assertSame('Petugas SCM pengganti', $cbe->approvals()->first()->signed_name);
        $this->rejects(fn () => app(Workflow::class)->startLegal($this->scm, $cbe->request_id, $cbe->request->version));
        $this->assertDatabaseCount('scm_legal_cases', 0);
    }

    public function test_inactive_assignee_cannot_sign_and_missing_profile_keeps_step_pending(): void
    {
        $cbe = $this->cbe();
        Storage::disk('scm')->delete(app(Signatures::class)->current($this->meyfi)->path);
        $this->rejects(fn () => $this->service->sign($this->meyfi, $cbe->id, $cbe->request->version));
        $this->assertSame('pending', $this->service->next($cbe)->status);
        $this->meyfi->employee->update(['is_active' => false]);
        $this->meyfi->unsetRelation('employee');
        $this->rejects(fn () => $this->service->sign($this->meyfi, $cbe->id, $cbe->request->version));
    }

    public function test_requested_default_master_is_seeded_without_replacing_existing_settings(): void
    {
        $this->seed(CbeApprovalPolicySeeder::class);
        $policy = CbeApprovalPolicy::where('company', 'APCA')->sole();
        $this->assertSame($this->meyfi->id, $policy->acknowledger_id);
        $this->assertSame($this->dipta->id, $policy->crosschecker_id);
        $this->assertSame($this->lenaldy->id, $policy->approver_id);
        $policy->update(['approver_id' => $this->outsider->id]);
        $this->seed(CbeApprovalPolicySeeder::class);
        $this->assertSame($this->outsider->id, $policy->fresh()->approver_id);
    }

    public function test_existing_final_cbe_can_explicitly_start_signatures_without_changing_its_snapshot(): void
    {
        $cbe = $this->cbe(false, false);
        $snapshot = $cbe->snapshot;
        $request = $this->service->startExisting($this->scm, $cbe->request_id, $cbe->request->version);
        $this->assertSame($snapshot, $cbe->fresh()->snapshot);
        $this->assertFalse($this->service->complete($cbe));
        $this->rejects(fn () => $this->service->startExisting($this->scm, $request->id, $request->version));
        foreach ([$this->meyfi, $this->dipta, $this->lenaldy] as $user) {
            $this->service->sign($user, $cbe->id, $cbe->request()->first()->version);
        }
        $this->assertTrue($this->service->complete($cbe));
    }
}
