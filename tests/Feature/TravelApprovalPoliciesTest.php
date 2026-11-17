<?php

namespace Tests\Feature;

use App\Filament\Pages\TravelApprovalRules;
use App\Filament\Pages\TravelLpj;
use App\Filament\Pages\TravelSpd;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Scm\UserSignature;
use App\Models\TravelApprovalPolicy;
use App\Models\TravelApprovalPolicyEvent;
use App\Models\TravelDocument;
use App\Models\User;
use App\Services\Travel\TravelAccess;
use App\Services\Travel\TravelApprovalPolicies;
use App\Services\Travel\TravelWorkflow;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TravelApprovalPoliciesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $author;

    private User $hr;

    private User $finance;

    private User $supervisor;

    private Department $department;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('testing', app()->environment());
        if (config('database.default') === 'pgsql') {
            $database = getenv('TRAVEL_TEST_DATABASE');
            $this->assertMatchesRegularExpression('/^ik_travel_test_[a-f0-9]{16}$/', $database ?: '');
            $this->assertSame($database, config('database.connections.pgsql.database'));
            $this->assertEmpty(config('database.connections.pgsql.url'));

            return;
        }
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('scm');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->department = Department::create(['code' => 'POLICY-QA', 'name' => 'Departemen Aturan QA']);
        $this->admin = User::factory()->create(['is_admin' => true, 'name' => 'Admin Aturan QA']);
        $this->author = $this->actor('Pengaju Aturan QA', ['travel.spd.create', 'travel.lpj.create']);
        $this->hr = $this->actor('HR Aturan QA', ['travel.approve'], 'manager');
        $this->finance = $this->actor('Finance Aturan QA', ['travel.approve']);
        $this->supervisor = $this->actor('Atasan Aturan QA', ['travel.approve'], 'manager');
        $this->author->employee->update(['supervisor_employee_id' => $this->supervisor->employee->id]);
        $this->author->unsetRelation('employee');
        $this->actingAs($this->author);
    }

    private function actor(string $name, array $permissions, string $level = 'staff'): User
    {
        $user = User::factory()->create(['is_admin' => false, 'name' => $name]);
        Employee::create(['user_id' => $user->id, 'department_id' => $this->department->id, 'name' => $name,
            'position' => $name, 'position_level' => $level, 'is_active' => true]);
        $user->directPermissions()->attach(Permission::whereIn('code', $permissions)->pluck('id'));
        $path = 'signatures/test-'.$user->id.'.png';
        Storage::disk('scm')->put($path, 'test signature');
        UserSignature::create(['user_id' => $user->id, 'path' => $path, 'mime' => 'image/png']);

        return $user->fresh();
    }

    private function rule(array $extra = []): array
    {
        return [...TravelApprovalPolicies::defaults(), 'department_id' => $this->department->id,
            'hr_id' => $this->hr->id, 'finance_id' => $this->finance->id, ...$extra];
    }

    public function test_hrga_manager_title_is_recognized_when_level_is_missing(): void
    {
        $this->hr->employee->update(['position_level' => null, 'position' => 'HRGA Manager']);
        $this->hr->unsetRelation('employee');
        $this->assertTrue(TravelAccess::manager($this->hr));
        $this->savePolicy();
        $this->hr->employee->update(['position_level' => 'staff']);
        $this->hr->unsetRelation('employee');
        $this->assertFalse(TravelAccess::manager($this->hr));
    }

    private function savePolicy(array $extra = []): TravelApprovalPolicy
    {
        return app(TravelApprovalPolicies::class)->save($this->admin, $this->rule($extra), null, 0);
    }

    private function input(): array
    {
        return [...app(TravelWorkflow::class)->defaults($this->author, 'spd'), 'purpose' => 'Perjalanan pengujian aturan',
            'depart_at' => '2026-11-16T08:00', 'return_at' => '2026-11-17T18:00',
            'routes' => [['from' => 'Jakarta', 'to' => 'Lokasi QA', 'mode' => 'Mobil']],
            'costs' => [['description' => 'Tunjangan', 'quantity' => '2', 'unit_cost' => '100000', 'notes' => '']]];
    }

    private function document(bool $submit = true): TravelDocument
    {
        $document = app(TravelWorkflow::class)->save($this->author, 'spd', null, 0, $this->input());

        return $submit ? app(TravelWorkflow::class)->submit($this->author, $document->id, $document->version) : $document;
    }

    private function khafafi(): User
    {
        $user = $this->actor('Khafafi', []);
        $user->update(['email' => 'riyankhafafi@kpmog.com', 'is_admin' => true]);

        return $user->fresh();
    }

    private function arrangedInput(array $extra = []): array
    {
        return [...$this->input(), 'requester_id' => $this->author->id, 'arrange_approvals' => true,
            'approver_ids' => ['supervisor' => $this->supervisor->id, 'finance' => $this->finance->id, 'hr' => $this->hr->id], ...$extra];
    }

    public function test_only_the_active_khafafi_sysadmin_account_can_arrange_documents(): void
    {
        $khafafi = $this->khafafi();
        $this->assertTrue(TravelAccess::canArrangeDocument($khafafi));
        $this->assertFalse(TravelAccess::canArrangeDocument($this->admin));
        $impostor = $this->actor('Khafafi', ['travel.spd.create', 'travel.configure']);
        $impostor->update(['is_admin' => true]);
        $this->assertFalse(TravelAccess::canArrangeDocument($impostor->fresh()));
        $khafafi->update(['is_admin' => false]);
        $this->assertFalse(TravelAccess::canArrangeDocument($khafafi->fresh()));
        $khafafi->update(['is_admin' => true]);
        $khafafi->employee->update(['is_active' => false]);
        $this->assertFalse(TravelAccess::canArrangeDocument($khafafi->fresh()));
    }

    public function test_khafafi_can_select_requester_and_signers_with_operator_audit_and_no_impersonated_signature(): void
    {
        $khafafi = $this->khafafi();
        $replacement = $this->actor('Atasan Pilihan Khafafi QA', ['travel.approve'], 'manager');
        $page = Livewire::actingAs($khafafi)->test(TravelSpd::class)->call('createDocument')
            ->assertSee('Pengaturan Khafafi')->set('input.requester_id', '')->assertSet('input.employee_id', '')
            ->set('input.requester_id', (string) $this->author->id)
            ->assertSet('input.employee_id', (string) $this->author->employee->id)
            ->set('input', $this->arrangedInput(['approver_ids' => ['supervisor' => $replacement->id, 'finance' => $this->finance->id, 'hr' => $this->hr->id]]))
            ->assertSee('wire:model.live="input.approver_ids.hr"', false)
            ->call('submitDocument')->assertHasErrors(['travel']);
        $document = TravelDocument::findOrFail($page->get('selected'));
        $this->assertSame('draft', $document->status);
        $this->assertSame(1, $this->author->notifications()->count());
        $document = app(TravelWorkflow::class)->submit($this->author, $document->id, $document->version);
        $this->assertSame('submitted', $document->status);
        $this->assertSame($khafafi->id, $document->created_by);
        $this->assertSame($this->author->id, $document->data['requester_id']);
        $this->assertSame($khafafi->id, $document->data['approval_override']['arranged_by']);
        $this->assertSame([$this->hr->id, $this->finance->id, $replacement->id], $document->currentApprovals()->pluck('user_id')->all());
        $this->assertSame('khafafi', $document->data['approval_policy']['source']);
        $this->assertSame($this->author->name, $document->prepared_signature['name']);
        $this->assertNotNull($document->prepared_signature['path']);
        $this->assertNotNull($document->prepared_signature['signed_at']);
        $this->assertArrayNotHasKey('submitted_by', $document->prepared_signature);
        $this->assertSame($this->author->id, $document->events()->where('action', 'submitted')->first()->actor_id);
        $this->assertSame(0, TravelApprovalPolicy::count());
        $this->get(route('travel.print', $document))->assertOk()->assertSee($replacement->name)
            ->assertDontSee('wire:model.live="input.approver_ids.', false);
        $this->actingAs($this->author);
        $this->assertSame($document->id, app(TravelWorkflow::class)->document($this->author, $document->id)->id);
    }

    public function test_other_users_including_sysadmins_cannot_forge_requester_or_document_override(): void
    {
        $this->savePolicy();
        foreach ([$this->author, $this->admin] as $actor) {
            foreach ([['requester_id' => $this->finance->id], ['arrange_approvals' => true]] as $changes) {
                try {
                    app(TravelWorkflow::class)->save($actor, 'spd', null, 0, [...$this->input(), ...$changes]);
                    $this->fail('An unauthorized user changed document administration.');
                } catch (HttpException $exception) {
                    $this->assertSame(403, $exception->getStatusCode());
                    $this->assertSame(0, TravelDocument::count());
                }
            }
            Livewire::actingAs($actor)->test(TravelSpd::class)->call('createDocument')->assertDontSee('Pengaturan Khafafi')
                ->assertDontSee('wire:model.live="input.requester_id"', false)
                ->assertDontSee('wire:model.live="input.approver_ids.', false);
        }
    }

    public function test_requester_can_edit_and_submit_but_cannot_change_khafafi_assignments(): void
    {
        $khafafi = $this->khafafi();
        $workflow = app(TravelWorkflow::class);
        $document = $workflow->save($khafafi, 'spd', null, 0, $this->arrangedInput());
        $this->assertTrue(TravelAccess::editable($this->author, $document));
        $this->actingAs($this->author);
        Livewire::test(TravelSpd::class)->call('openDocument', $document->id)->assertDontSee('Pengaturan Khafafi')
            ->assertDontSee('wire:model.live="input.approver_ids.', false);
        foreach ([['requester_id' => $this->finance->id], ['arrange_approvals' => false],
            ['approver_ids' => ['hr' => $this->supervisor->id, 'finance' => $this->finance->id, 'supervisor' => $this->hr->id]]] as $changes) {
            try {
                $workflow->save($this->author, 'spd', $document->id, $document->version, [...$workflow->formData($document), ...$changes]);
                $this->fail('Requester changed Khafafi assignments.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
                $this->assertSame(1, $document->fresh()->version);
            }
        }
        $document = $workflow->save($this->author, 'spd', $document->id, $document->version, [...$workflow->formData($document), 'notes' => 'Catatan pengaju']);
        $document = $workflow->submit($this->author, $document->id, $document->version);
        $this->assertSame($khafafi->id, $document->data['approval_override']['arranged_by']);
        $this->assertSame($this->author->name, $document->prepared_signature['name']);
        $this->assertArrayNotHasKey('submitted_by', $document->prepared_signature);
        $this->assertSame([$this->hr->id, $this->finance->id, $this->supervisor->id], $document->currentApprovals()->pluck('user_id')->all());
    }

    public function test_overrides_still_require_eligible_distinct_signers_without_requester_self_approval(): void
    {
        $khafafi = $this->khafafi();
        $workflow = app(TravelWorkflow::class);
        foreach ([['hr' => $this->finance->id, 'finance' => $this->hr->id, 'supervisor' => $this->supervisor->id],
            ['hr' => $this->hr->id, 'finance' => $this->hr->id, 'supervisor' => $this->supervisor->id],
            ['hr' => $this->hr->id, 'finance' => $this->author->id, 'supervisor' => $this->supervisor->id],
            ['hr' => '', 'finance' => '', 'supervisor' => '']] as $ids) {
            $document = $workflow->save($khafafi, 'spd', null, 0, $this->arrangedInput(['approver_ids' => $ids]));
            try {
                $workflow->submit($khafafi, $document->id, $document->version);
                $this->fail('Invalid override submitted.');
            } catch (ValidationException) {
                $this->assertSame('draft', $document->fresh()->status);
                $this->assertSame(0, $document->approvals()->count());
            }
        }
        $this->author->directPermissions()->attach(Permission::where('code', 'travel.approve')->value('id'));
        $document = $workflow->save($khafafi, 'spd', null, 0, $this->arrangedInput(['approver_ids' => ['hr' => $this->hr->id, 'finance' => $this->author->id, 'supervisor' => $this->supervisor->id]]));
        try {
            $workflow->submit($khafafi, $document->id, $document->version);
            $this->fail('Requester approved own document.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('sendiri', $exception->errors()['approval_policy'][0]);
        }
    }

    public function test_khafafi_can_restore_master_route_and_revoked_authorizer_cannot_submit_an_override(): void
    {
        $policy = $this->savePolicy();
        $khafafi = $this->khafafi();
        $workflow = app(TravelWorkflow::class);
        $document = $workflow->save($khafafi, 'spd', null, 0, $this->arrangedInput());
        $document = $workflow->save($khafafi, 'spd', $document->id, $document->version, [...$workflow->formData($document), 'arrange_approvals' => false]);
        $this->assertNull($document->data['approval_override']);
        $document = $workflow->submit($this->author, $document->id, $document->version);
        $this->assertSame($policy->id, $document->data['approval_policy']['id']);
        $pending = $workflow->save($khafafi, 'spd', null, 0, $this->arrangedInput());
        $khafafi->update(['is_admin' => false]);
        $this->expectException(ValidationException::class);
        $workflow->submit($this->author, $pending->id, $pending->version);
    }

    public function test_lpj_allows_khafafi_assignments_but_keeps_approved_spd_identity_and_scope(): void
    {
        $khafafi = $this->khafafi();
        $workflow = app(TravelWorkflow::class);
        $spd = $workflow->save($khafafi, 'spd', null, 0, $this->arrangedInput());
        $spd = $workflow->submit($this->author, $spd->id, $spd->version);
        foreach ([$this->hr, $this->finance, $this->supervisor] as $actor) {
            $spd = $workflow->decide($actor, $spd->id, $spd->version, true);
        }
        $page = Livewire::actingAs($khafafi)->test(TravelLpj::class)->call('createDocument')->set('input.spd_id', (string) $spd->id)
            ->assertSet('input.requester_id', (string) $this->author->id)
            ->set('input.arrange_approvals', true)
            ->set('input.approver_ids', $this->arrangedInput()['approver_ids'])
            ->set('input.expenses', [['date' => '2026-11-16', 'description' => 'Biaya aktual', 'transport' => '100000']])
            ->call('submitDocument')->assertHasErrors(['travel']);
        $lpj = TravelDocument::findOrFail($page->get('selected'));
        $lpj = $workflow->submit($this->author, $lpj->id, $lpj->version);
        $this->assertSame('submitted', $lpj->status);
        $this->assertSame($spd->id, $lpj->spd_id);
        $this->assertSame($spd->employee_snapshot, $lpj->employee_snapshot);
        $this->assertSame($spd->department_id, $lpj->department_id);
        $this->assertSame($this->author->id, $lpj->data['requester_id']);
        $this->assertSame($khafafi->id, $lpj->data['approval_policy']['arranged_by']);
    }

    public function test_selected_requester_has_owner_scope_even_when_the_traveler_is_different(): void
    {
        $khafafi = $this->khafafi();
        $traveler = $this->actor('Pelaksana Lain QA', ['travel.spd.create']);
        $workflow = app(TravelWorkflow::class);
        $document = $workflow->save($khafafi, 'spd', null, 0, $this->arrangedInput(['employee_id' => $traveler->employee->id]));
        $this->assertNotSame($document->employee_id, $this->author->employee->id);
        $this->assertNotSame($document->created_by, $this->author->id);
        $this->assertSame($document->id, $workflow->document($this->author, $document->id)->id);
        $this->assertTrue(TravelAccess::editable($this->author, $document));
        $document = $workflow->save($this->author, 'spd', $document->id, $document->version, [...$workflow->formData($document), 'notes' => 'Catatan pembuat terpilih']);
        $this->assertSame($traveler->employee->id, $document->employee_id);
        $document = $workflow->submit($this->author, $document->id, $document->version);
        foreach ([$this->hr, $this->finance, $this->supervisor] as $actor) {
            $document = $workflow->decide($actor, $document->id, $document->version, true);
        }
        $this->assertTrue($workflow->eligibleSpds($this->author)->whereKey($document->id)->exists());
        $this->assertFalse(TravelAccess::editable($khafafi, $document));
        $outside = $this->actor('Bukan Pengaju QA', ['travel.spd.create', 'travel.lpj.create']);
        $this->assertFalse(TravelAccess::query($outside)->whereKey($document->id)->exists());
        $this->assertFalse($workflow->eligibleSpds($outside)->whereKey($document->id)->exists());
    }

    public function test_master_form_supports_create_edit_copy_and_delete_with_audit(): void
    {
        $page = Livewire::actingAs($this->admin)->test(TravelApprovalRules::class)->assertSee('Belum ada aturan approval')
            ->set('rule', $this->rule())->call('saveRule')->assertHasNoErrors();
        $policy = TravelApprovalPolicy::sole();
        $this->assertSame(1, $policy->version);
        $page->call('editRule', $policy->id)->set('rule.is_active', false)->call('saveRule')->assertHasNoErrors();
        $this->assertFalse($policy->fresh()->is_active);
        $this->assertSame(2, $policy->fresh()->version);
        $page->call('copyRule', $policy->id)->assertSet('editingId', null)->assertSet('version', 0)
            ->set('rule.company', 'KPMOG')->set('rule.is_active', true)->call('saveRule')->assertHasNoErrors();
        $this->assertSame(2, TravelApprovalPolicy::count());
        $page->call('deleteRule', $policy->id, 2)->assertHasNoErrors();
        $this->assertSame(1, TravelApprovalPolicy::count());
        $this->assertSame(3, TravelApprovalPolicyEvent::where('policy_id', $policy->id)->count());
        $this->assertSame('deleted', TravelApprovalPolicyEvent::where('policy_id', $policy->id)->latest('id')->first()->action);
    }

    public function test_signer_search_shows_ineligible_accounts_with_reasons_and_keeps_selected_names_visible(): void
    {
        $withoutAccess = $this->actor('Manajer Belum Berizin QA', [], 'manager');
        $inactive = $this->actor('Pemeriksa Nonaktif QA', ['travel.approve'], 'manager');
        $inactive->employee->update(['is_active' => false]);
        $page = Livewire::actingAs($this->admin)->test(TravelApprovalRules::class)
            ->assertSee('Cari nama pemeriksa')->assertSee('Belum memiliki izin persetujuan SPD/LPJ')
            ->assertSee('Jenjang belum minimal Manager')->assertSee('Akun tidak aktif');
        $inspect = function (string $html, string $role, int $userId): ?\DOMElement {
            $dom = new \DOMDocument;
            $previous = libxml_use_internal_errors(true);
            $dom->loadHTML($html, LIBXML_NONET);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            return (new \DOMXPath($dom))->query('//select[@id="travel-rule-'.$role.'"]/option[@value="'.$userId.'"]')->item(0);
        };
        $this->assertTrue($inspect($page->html(), 'hr', $withoutAccess->id)->hasAttribute('disabled'));
        $this->assertTrue($inspect($page->html(), 'hr', $this->finance->id)->hasAttribute('disabled'));
        $this->assertTrue($inspect($page->html(), 'finance', $inactive->id)->hasAttribute('disabled'));
        $this->assertFalse($inspect($page->html(), 'hr', $this->hr->id)->hasAttribute('disabled'));
        $this->assertFalse($inspect($page->html(), 'finance', $this->finance->id)->hasAttribute('disabled'));
        $page->set('rule.hr_id', $this->hr->id)->set('signerSearch', 'manajer belum')
            ->assertSee($withoutAccess->name)->assertSee($this->hr->name);
        $this->assertNull($inspect($page->html(), 'finance', $this->finance->id));
        $page->set('signerSearch', 'NamaTidakAda987')->assertSee('Tidak ada nama yang sesuai pencarian.');
        $this->assertNotNull($inspect($page->html(), 'hr', $this->hr->id));
        $page->set('signerSearch', '')->set('rule', $this->rule(['hr_id' => $withoutAccess->id]))
            ->call('saveRule')->assertHasErrors(['approval_policy']);
        $this->assertSame(0, TravelApprovalPolicy::count());
        $this->assertFalse($withoutAccess->fresh()->hasPermission('travel.approve'));
    }

    public function test_empty_hr_eligibility_and_missing_selection_explain_access_setup_in_indonesian(): void
    {
        $this->hr->directPermissions()->detach();
        $this->supervisor->directPermissions()->detach();
        $page = Livewire::actingAs($this->admin)->test(TravelApprovalRules::class)
            ->assertSee('Belum ada akun yang memenuhi syarat untuk HRGA / HR Manager.')
            ->assertSee('Setujui SPD dan LPJ yang ditugaskan')->assertSee('Buka User Management (tab baru)')
            ->assertSee('novalidate', false)->set('rule', $this->rule(['hr_id' => '']))
            ->call('saveRule')->assertHasErrors(['hr_id'])->assertSee('Pilih HRGA / HR Manager.');
        $this->assertSame(0, TravelApprovalPolicy::count());
        $this->hr->directPermissions()->attach(Permission::where('code', 'travel.approve')->value('id'));
        $page->set('rule.hr_id', $this->hr->id)->call('saveRule')->assertHasNoErrors();
        $this->assertSame($this->hr->id, TravelApprovalPolicy::sole()->hr_id);
    }

    public function test_khafafi_master_can_assign_active_users_without_global_permission_and_review_access_stays_document_scoped(): void
    {
        $khafafi = $this->khafafi();
        $hr = $this->actor('HR Penugasan QA', [], 'manager');
        $finance = $this->actor('Finance Penugasan QA', []);
        $head = $this->actor('Atasan Pilihan QA', []);
        $ids = ['hr' => $hr->id, 'finance' => $finance->id, 'supervisor' => $head->id];
        foreach ([$hr, $finance, $head] as $actor) {
            $this->assertFalse(TravelAccess::canOpen($actor, 'spd'));
            $this->assertFalse(TravelAccess::approver($actor));
        }
        $page = Livewire::actingAs($khafafi)->test(TravelApprovalRules::class)
            ->assertSee('Khusus Khafafi: pilih akun aktif yang bertugas.')
            ->set('rule', $this->rule(['hr_id' => $hr->id, 'finance_id' => $finance->id, 'supervisor_id' => $head->id]))
            ->call('saveRule')->assertHasNoErrors();
        $policy = TravelApprovalPolicy::sole();
        $this->assertTrue(TravelApprovalPolicyEvent::where('policy_id', $policy->id)->sole()->details['assigned_access']);
        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML($page->html(), LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($dom);
        foreach ($ids as $role => $userId) {
            $option = $xpath->query('//select[@id="travel-rule-'.$role.'"]/option[@value="'.$userId.'"]')->item(0);
            $this->assertNotNull($option);
            $this->assertFalse($option->hasAttribute('disabled'));
        }
        $this->actingAs($this->author);
        $document = $this->document();
        $this->assertSame($ids, $document->data['approver_ids']);
        $this->assertSame('khafafi-master', $document->data['approval_policy']['source']);
        $this->assertTrue($document->data['approval_policy']['assigned_access']);
        $this->assertSame($this->supervisor->id, $document->employee_snapshot['supervisor_user_id']);
        $this->assertSame($head->id, $document->currentApprovals()->where('role', 'supervisor')->value('user_id'));
        $workflow = app(TravelWorkflow::class);
        app(TravelApprovalPolicies::class)->save($this->admin, $this->rule(['company' => 'KPMOG']), null, 0);
        $other = $workflow->save($this->author, 'spd', null, 0, [...$this->input(), 'company' => 'KPMOG']);
        $other = $workflow->submit($this->author, $other->id, $other->version);
        foreach ([$hr, $finance, $head] as $actor) {
            $this->assertTrue(TravelAccess::canOpen($actor, 'spd'));
            $this->assertFalse(TravelAccess::canOpen($actor, 'lpj'));
            $this->assertFalse(TravelAccess::canCreate($actor, 'spd'));
            $this->assertFalse(TravelAccess::has($actor->fresh(), 'travel.approve'));
            $this->assertFalse(TravelAccess::editable($actor, $document));
            $this->assertSame([$document->id], TravelAccess::query($actor)->pluck('id')->all());
            $this->actingAs($actor)->get(TravelSpd::getUrl())->assertOk();
            $this->get(route('travel.print', $document))->assertOk();
            $this->get(route('travel.print', $other))->assertNotFound();
            $this->get(TravelApprovalRules::getUrl())->assertForbidden();
        }
        $this->assertFalse($workflow->canDecide($finance, $document));
        $this->assertFalse($workflow->canDecide($head, $document));
        $outsider = $this->actor('Akun Bukan Pemeriksa QA', []);
        $this->assertFalse(TravelAccess::canOpen($outsider, 'spd'));
        $this->assertFalse($workflow->canDecide($outsider, $document));
        $this->assertFalse(TravelAccess::query($outsider)->whereKey($document->id)->exists());
        $page->call('deleteRule', $policy->id, $policy->version)->assertHasNoErrors();
        $khafafi->update(['is_admin' => false]);
        foreach ([$hr, $finance, $head] as $actor) {
            $this->assertTrue($workflow->canDecide($actor, $document));
            $document = $workflow->decide($actor, $document->id, $document->version, true);
        }
        $this->assertSame('approved', $document->status);
        $this->assertSame(0, $hr->directPermissions()->count());
        $this->assertSame(0, $finance->directPermissions()->count());
        $this->assertSame(0, $head->directPermissions()->count());
    }

    public function test_only_khafafi_can_configure_assignment_access_and_invalid_audit_cannot_authorize_it(): void
    {
        $khafafi = $this->khafafi();
        $hr = $this->actor('HR Tanpa Hak Umum QA', [], 'manager');
        $finance = $this->actor('Finance Tanpa Hak Umum QA', []);
        $head = $this->actor('Atasan Tanpa Hak Umum QA', []);
        $input = $this->rule(['hr_id' => $hr->id, 'finance_id' => $finance->id, 'supervisor_id' => $head->id, 'assigned_access' => true]);
        foreach ([$this->admin, $this->actor('Konfigurator QA', ['travel.configure'])] as $actor) {
            try {
                app(TravelApprovalPolicies::class)->save($actor, $input, null, 0);
                $this->fail('An ordinary configurator bypassed approval permission.');
            } catch (ValidationException) {
                $this->assertSame(0, TravelApprovalPolicy::count());
            }
        }
        $policy = app(TravelApprovalPolicies::class)->save($khafafi, $input, null, 0);
        $document = $this->document(false);
        $event = TravelApprovalPolicyEvent::where('policy_id', $policy->id)->sole();
        $details = $event->details;
        $tampered = $details;
        $tampered['after']['version'] = $policy->version + 1;
        $event->update(['details' => $tampered]);
        $this->assertNull(app(TravelApprovalPolicies::class)->preview('APCA', $this->department->id, 'spd', $document->employee_snapshot, $this->author->id)['plan']);
        $event->update(['details' => $details, 'actor_id' => $this->admin->id]);
        $this->assertNull(app(TravelApprovalPolicies::class)->preview('APCA', $this->department->id, 'spd', $document->employee_snapshot, $this->author->id)['plan']);
        $event->update(['actor_id' => $khafafi->id]);
        $khafafi->update(['is_admin' => false]);
        $this->assertNull(app(TravelApprovalPolicies::class)->preview('APCA', $this->department->id, 'spd', $document->employee_snapshot, $this->author->id)['plan']);
        $this->assertSame('draft', $document->fresh()->status);
        $this->assertSame(0, $document->approvals()->count());
    }

    public function test_manual_khafafi_assignments_also_allow_only_assigned_spd_and_lpj_reviews_without_global_permissions(): void
    {
        $khafafi = $this->khafafi();
        $hr = $this->actor('HR Manual Penugasan QA', [], 'manager');
        $finance = $this->actor('Finance Manual Penugasan QA', []);
        $head = $this->actor('Atasan Manual Penugasan QA', []);
        $ids = ['hr' => $hr->id, 'finance' => $finance->id, 'supervisor' => $head->id];
        $this->assertArrayHasKey($finance->id, collect(app(TravelWorkflow::class)->approvers($khafafi))->keyBy('id')->all());
        $this->assertArrayNotHasKey($finance->id, collect(app(TravelWorkflow::class)->approvers($this->admin))->keyBy('id')->all());
        $workflow = app(TravelWorkflow::class);
        $document = $workflow->save($khafafi, 'spd', null, 0, $this->arrangedInput(['approver_ids' => $ids]));
        $document = $workflow->submit($this->author, $document->id, $document->version);
        foreach ([$hr, $finance, $head] as $actor) {
            $document = $workflow->decide($actor, $document->id, $document->version, true);
        }
        $lpj = $workflow->save($khafafi, 'lpj', null, 0, [...$workflow->defaults($khafafi, 'lpj'),
            'number' => '', 'spd_id' => $document->id, 'requester_id' => $this->author->id,
            'arrange_approvals' => true, 'approver_ids' => $ids,
            'expenses' => [['date' => '2026-11-16', 'description' => 'Biaya QA', 'transport' => '100000']]]);
        $this->assertFalse(TravelAccess::canOpen($finance, 'lpj'));
        $lpj = $workflow->submit($this->author, $lpj->id, $lpj->version);
        $this->assertTrue(TravelAccess::canOpen($finance, 'lpj'));
        $this->actingAs($finance)->get(TravelLpj::getUrl())->assertOk();
        $this->assertFalse($workflow->canDecide($finance, $lpj));
        foreach ([$hr, $finance, $head] as $actor) {
            $lpj = $workflow->decide($actor, $lpj->id, $lpj->version, true);
        }
        $this->assertSame('approved', $lpj->status);
        $this->assertTrue($lpj->data['approval_policy']['assigned_access']);
        $this->assertSame('khafafi', $lpj->data['approval_policy']['source']);
        $this->assertFalse(TravelAccess::has($finance->fresh(), 'travel.approve'));
        $head->employee->update(['is_active' => false]);
        $this->assertFalse(TravelAccess::canOpen($head->fresh(), 'spd'));
        $this->assertFalse(TravelAccess::canOpen($head->fresh(), 'lpj'));
    }

    public function test_configuration_permission_is_separate_from_create_view_and_approve(): void
    {
        $this->assertFalse(TravelApprovalRules::canAccess());
        $this->get(TravelApprovalRules::getUrl())->assertForbidden();
        try {
            app(TravelApprovalPolicies::class)->save($this->author, $this->rule(), null, 0);
            $this->fail('Requester configured an approval rule.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $configurator = $this->actor('Pengatur Aturan QA', ['travel.configure']);
        Livewire::actingAs($configurator)->test(TravelApprovalRules::class)->set('rule', $this->rule())->call('saveRule')->assertHasNoErrors();
        $this->actingAs($configurator);
        $this->assertFalse(TravelSpd::canAccess());
        $this->assertFalse($configurator->hasPermission('scm.configure'));
        $this->assertFalse($configurator->hasPermission('travel.approve'));
    }

    public function test_specific_document_rule_wins_and_company_department_and_active_state_are_respected(): void
    {
        $all = $this->savePolicy();
        $otherHr = $this->actor('HR Khusus SPD QA', ['travel.approve'], 'manager');
        $specific = $this->savePolicy(['document_kind' => 'SPD', 'hr_id' => $otherHr->id]);
        $document = $this->document(false);
        $service = app(TravelApprovalPolicies::class);
        $arguments = ['APCA', $this->department->id, 'spd', $document->employee_snapshot, $this->author->id];
        $this->assertSame($specific->id, $service->resolve(...$arguments)['policy']['id']);
        $this->assertSame($all->id, $service->resolve('APCA', $this->department->id, 'lpj', $document->employee_snapshot, $this->author->id)['policy']['id']);
        $this->assertNull($service->preview('KPMOG', $this->department->id, 'spd', $document->employee_snapshot, $this->author->id)['plan']);
        $otherDepartment = Department::create(['code' => 'POLICY-OTHER', 'name' => 'Departemen Lain QA']);
        $this->assertNull($service->preview('APCA', $otherDepartment->id, 'spd', $document->employee_snapshot, $this->author->id)['plan']);
        $service->save($this->admin, $this->rule(['document_kind' => 'SPD', 'hr_id' => $otherHr->id, 'is_active' => false]), $specific->id, 1);
        $this->assertSame($all->id, $service->resolve(...$arguments)['policy']['id']);
    }

    public function test_draft_is_allowed_without_a_rule_but_manual_ids_cannot_submit_it(): void
    {
        $document = app(TravelWorkflow::class)->save($this->author, 'spd', null, 0,
            [...$this->input(), 'approver_ids' => ['hr' => $this->hr->id, 'finance' => $this->finance->id, 'supervisor' => $this->supervisor->id]]);
        $this->assertSame([], $document->data['approver_ids']);
        try {
            app(TravelWorkflow::class)->submit($this->author, $document->id, $document->version);
            $this->fail('Submitted without a master rule.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('approval_policy', $exception->errors());
            $this->assertSame('draft', $document->fresh()->status);
            $this->assertSame(0, $document->approvals()->count());
        }
    }

    public function test_document_form_displays_master_names_and_ignores_forged_signer_selection(): void
    {
        $policy = $this->savePolicy();
        $unassigned = $this->actor('Bukan Pemeriksa QA', ['travel.approve'], 'manager');
        $page = Livewire::test(TravelSpd::class)->call('createDocument')->set('input', $this->input())
            ->assertSee('HR Aturan QA')->assertSee('Finance Aturan QA')->assertSee('Atasan Aturan QA');
        $this->assertStringNotContainsString('wire:model.live="input.approver_ids.', $page->html());
        $page->set('input.approver_ids', ['hr' => $unassigned->id, 'finance' => $unassigned->id, 'supervisor' => $unassigned->id])
            ->set('input.approval_policy', ['source' => 'khafafi-master', 'assigned_access' => true])
            ->call('submitDocument')->assertHasNoErrors();
        $document = TravelDocument::findOrFail($page->get('selected'));
        $this->assertSame([$this->hr->id, $this->finance->id, $this->supervisor->id], $document->currentApprovals()->pluck('user_id')->all());
        $this->assertSame($policy->id, $document->data['approval_policy']['id']);
        $this->assertSame(1, $document->data['approval_policy']['version']);
        $this->assertArrayNotHasKey('assigned_access', $document->data['approval_policy']);
    }

    public function test_changed_or_deleted_master_does_not_reassign_running_documents(): void
    {
        $policy = $this->savePolicy();
        $document = $this->document();
        $snapshot = $document->getRawOriginal();
        $steps = $document->approvals()->get()->map->getRawOriginal()->all();
        $replacement = $this->actor('HR Pengganti QA', ['travel.approve'], 'manager');
        $service = app(TravelApprovalPolicies::class);
        $service->save($this->admin, $this->rule(['hr_id' => $replacement->id]), $policy->id, 1);
        $service->delete($this->admin, $policy->id, 2);
        $this->assertSame($snapshot, $document->fresh()->getRawOriginal());
        $this->assertSame($steps, $document->approvals()->get()->map->getRawOriginal()->all());
        $this->assertTrue(app(TravelWorkflow::class)->canDecide($this->hr, $document));
        $this->assertFalse(app(TravelWorkflow::class)->canDecide($replacement, $document));
        foreach ([$this->hr, $this->finance, $this->supervisor] as $actor) {
            $document = app(TravelWorkflow::class)->decide($actor, $document->id, $document->version, true);
        }
        $this->assertSame('approved', $document->status);
        $this->assertSame(1, $document->data['approval_policy']['version']);
        $this->get(route('travel.print', $document))->assertOk()->assertSee('HR Aturan QA')->assertDontSee('HR Pengganti QA');
    }

    public function test_returned_document_takes_latest_rule_only_on_new_submission(): void
    {
        $policy = $this->savePolicy();
        $document = $this->document();
        $document = app(TravelWorkflow::class)->decide($this->hr, $document->id, $document->version, false, 'Perbaiki catatan.');
        $replacement = $this->actor('HR Putaran Kedua QA', ['travel.approve'], 'manager');
        app(TravelApprovalPolicies::class)->save($this->admin, $this->rule(['hr_id' => $replacement->id]), $policy->id, 1);
        $oldSteps = $document->approvals()->get()->map->getRawOriginal()->all();
        $document = app(TravelWorkflow::class)->save($this->author, 'spd', $document->id, $document->version, app(TravelWorkflow::class)->formData($document));
        $document = app(TravelWorkflow::class)->submit($this->author, $document->id, $document->version);
        $this->assertSame(2, $document->approval_round);
        $this->assertSame(2, $document->data['approval_policy']['version']);
        $this->assertSame($replacement->id, $document->currentApprovals()->first()->user_id);
        $this->assertSame($oldSteps, $document->approvals()->where('round', 1)->get()->map->getRawOriginal()->all());
    }

    public function test_master_rejects_invalid_signers_duplicate_scope_stale_version_and_scope_changes(): void
    {
        $service = app(TravelApprovalPolicies::class);
        foreach ([['hr_id' => $this->finance->id], ['finance_id' => $this->hr->id], ['finance_id' => $this->author->id]] as $invalid) {
            try {
                $service->save($this->admin, $this->rule($invalid), null, 0);
                $this->fail('Invalid master approvers accepted.');
            } catch (ValidationException) {
                $this->assertSame(0, TravelApprovalPolicy::count());
            }
        }
        $policy = $this->savePolicy();
        foreach ([[null, 0, []], [$policy->id, 0, []], [$policy->id, 1, ['company' => 'KPMOG']]] as [$id, $version, $changes]) {
            try {
                $service->save($this->admin, $this->rule($changes), $id, $version);
                $this->fail('Duplicate, stale or changed rule scope accepted.');
            } catch (ValidationException) {
                $this->assertSame(1, $policy->fresh()->version);
                $this->assertSame(1, TravelApprovalPolicy::count());
            }
        }
        $this->finance->employee->update(['is_active' => false]);
        $draft = $this->document(false);
        $this->expectException(ValidationException::class);
        app(TravelWorkflow::class)->submit($this->author, $draft->id, $draft->version);
    }
}
