<?php

namespace Tests\Feature;

use App\Filament\Pages\TravelLpj;
use App\Filament\Pages\TravelSpd;
use App\Models\Department;
use App\Models\Employee;
use App\Models\FinanceAccount;
use App\Models\Permission;
use App\Models\Scm\ProjectNumbering;
use App\Models\Scm\UserSignature;
use App\Models\TravelApprovalPolicy;
use App\Models\TravelDocument;
use App\Models\User;
use App\Services\Scm\Access;
use App\Services\Travel\TravelAccess;
use App\Services\Travel\TravelApprovalPolicies;
use App\Services\Travel\TravelWorkflow;
use App\Services\Finance\BudgetMasters;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TravelDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private User $hr;

    private User $finance;

    private User $supervisor;

    private User $outsider;

    private Department $department;

    private User $policyAdmin;

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
        $this->department = Department::create(['code' => 'TRAVEL-QA', 'name' => 'Departemen Pengujian']);
        $other = Department::create(['code' => 'TRAVEL-OTHER', 'name' => 'Departemen Lain']);
        $this->hr = $this->actor($this->department, 'HR Manager QA', ['travel.approve'], 'manager');
        $this->finance = $this->actor($this->department, 'Finance QA', ['travel.approve']);
        $this->supervisor = $this->actor($this->department, 'Atasan QA', ['travel.approve'], 'manager');
        $this->requester = $this->actor($this->department, 'Karyawan QA', ['travel.spd.create', 'travel.lpj.create']);
        $this->requester->employee->update(['supervisor_employee_id' => $this->supervisor->employee->id]);
        $this->requester->unsetRelation('employee');
        $this->outsider = $this->actor($other, 'Pengguna di Luar Cakupan', ['travel.spd.view', 'travel.lpj.view', 'travel.approve']);
        $this->policyAdmin = User::factory()->create(['is_admin' => true]);
        foreach (['APCA', 'KPMOG'] as $company) {
            foreach (['HO', 'PROJECT'] as $travelType) {
                app(TravelApprovalPolicies::class)->save($this->policyAdmin,
                    [...TravelApprovalPolicies::defaults(), 'company' => $company, 'department_id' => $this->department->id,
                        'travel_type' => $travelType, 'hr_id' => $this->hr->id, 'finance_id' => $this->finance->id], null, 0);
            }
        }
        $this->actingAs($this->requester);
    }

    private function actor(Department $department, string $name, array $permissions, string $level = 'staff'): User
    {
        $user = User::factory()->create(['is_admin' => false, 'name' => $name]);
        Employee::create(['user_id' => $user->id, 'department_id' => $department->id, 'name' => $name, 'position' => $name, 'position_level' => $level, 'is_active' => true]);
        $user->directPermissions()->attach(Permission::whereIn('code', $permissions)->pluck('id'));
        $path = 'signatures/test-'.$user->id.'.png';
        Storage::disk('scm')->put($path, 'test signature');
        UserSignature::create(['user_id' => $user->id, 'path' => $path, 'mime' => 'image/png']);

        return $user->fresh();
    }

    public function test_dashboard_spd_shows_its_current_approval_and_respects_document_access(): void
    {
        $workflow = app(TravelWorkflow::class);
        $document = $this->draft();
        $document = $workflow->submit($this->requester, $document->id, $document->version);
        $document = $workflow->decide($this->hr, $document->id, $document->version, true);

        $detailPath = '/panel/perjalanan-dinas/spd?dokumen='.$document->id;
        $this->get('/panel')->assertOk()->assertSeeInOrder([
            $document->number, 'Lokasi Pengujian', '16/11/2026 s.d. 18/11/2026', 'Menunggu persetujuan Manager Project',
        ])->assertSee($detailPath, false);
        $this->get($detailPath)->assertOk();

        $this->actingAs($this->outsider)->get('/panel')->assertOk()
            ->assertSeeText('Belum ada SPD yang dapat Anda lihat.')
            ->assertDontSeeText($document->number);

        $document = $workflow->decide($this->finance, $document->id, $document->version, false, 'Perbarui rincian kegiatan.');
        $this->actingAs($this->requester)->get('/panel')->assertOk()
            ->assertSeeText($document->displayNumber())
            ->assertSeeText('Dikembalikan untuk revisi');

        $workflow->submit($this->requester, $document->id, $document->version);
        $this->get('/panel')->assertOk()
            ->assertSeeText('Menunggu persetujuan HRGA / HR Manager')
            ->assertDontSeeText('Menunggu persetujuan Atasan Langsung');
    }

    public function test_signatures_notifications_and_revision_follow_the_approval_sequence(): void
    {
        $workflow = app(TravelWorkflow::class);
        $document = $this->draft();
        Storage::disk('scm')->delete('signatures/test-'.$this->requester->id.'.png');
        try {
            $workflow->submit($this->requester, $document->id, $document->version);
            $this->fail('Submission without a profile signature was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('travel', $exception->errors());
        }

        Storage::disk('scm')->put('signatures/test-'.$this->requester->id.'.png', 'test signature');
        $document = $workflow->submit($this->requester, $document->id, $document->version);
        $this->assertSame(1, $this->hr->notifications()->count());
        $this->assertSame(0, $this->finance->notifications()->count());
        Storage::disk('scm')->delete('signatures/test-'.$this->hr->id.'.png');
        try {
            $workflow->decide($this->hr, $document->id, $document->version, true);
            $this->fail('Approval without a profile signature was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('travel', $exception->errors());
            $this->assertSame('pending', $document->currentApprovals()->first()->status);
        }
        Storage::disk('scm')->put('signatures/test-'.$this->hr->id.'.png', 'test signature');
        $document = $workflow->decide($this->hr, $document->id, $document->version, true);
        $this->assertSame(1, $this->finance->notifications()->count());
        $document = $workflow->decide($this->finance, $document->id, $document->version, false, 'Perbaiki rincian');
        $this->assertSame('001/SPD/11-2026 Rev 1', $document->displayNumber());
        $document = $workflow->save($this->requester, 'spd', $document->id, $document->version, $this->input());
        $document = $workflow->submit($this->requester, $document->id, $document->version);
        $document = $workflow->decide($this->hr, $document->id, $document->version, false, 'Perbaiki lagi');
        $this->assertSame('001/SPD/11-2026 Rev 2', $document->displayNumber());
        $this->assertSame('2', $document->revision);
    }

    public function test_spd_automatically_shows_its_department_budget_when_only_one_allocation_matches(): void
    {
        $account = FinanceAccount::create(['company' => 'APCA', 'code' => 'SPD-BUDGET', 'name' => 'Biaya perjalanan',
            'is_active' => true, 'created_by' => $this->policyAdmin->id, 'updated_by' => $this->policyAdmin->id]);
        $masters = app(BudgetMasters::class);
        $budget = $masters->createBudget($this->policyAdmin, ['company' => 'APCA', 'year' => today()->year, 'amount' => '1000']);
        $scope = $masters->createScope($this->policyAdmin, $budget->id, ['need_type' => 'HO', 'amount' => '1000']);
        $masters->createLine($this->policyAdmin, $scope->id, ['department_id' => $this->department->id, 'account_id' => $account->id, 'amount' => '800']);
        $line = $scope->lines()->sole();

        Livewire::actingAs($this->requester)->test(TravelSpd::class)->call('createDocument')
            ->assertSet('input.finance_budget_line_id', (string) $line->id)
            ->assertSee('Sisa IDR 800,00')->assertDontSee('SPD-BUDGET')->assertDontSee('Biaya perjalanan');
    }

    public function test_spd_revision_field_renders_instead_of_showing_blade_source(): void
    {
        Livewire::actingAs($this->requester)->test(TravelSpd::class)->call('createDocument')
            ->assertSeeHtml('aria-label="Revisi SPD"')
            ->assertDontSee("@include('travel.partials.field', ['field' => 'revision'");
    }

    public function test_spd_shows_project_budget_after_project_is_selected(): void
    {
        $account = FinanceAccount::create(['company' => 'KPMOG', 'code' => 'SPD-PROJECT-BUDGET', 'name' => 'Biaya perjalanan project',
            'is_active' => true, 'created_by' => $this->policyAdmin->id, 'updated_by' => $this->policyAdmin->id]);
        $project = ProjectNumbering::withoutEvents(fn () => ProjectNumbering::create([
            'code' => 'SPD-BUDGET-PROJECT', 'name' => 'Project Anggaran SPD', 'is_active' => true, 'version' => 1,
        ]));
        $masters = app(BudgetMasters::class);
        $budget = $masters->createBudget($this->policyAdmin, ['company' => 'KPMOG', 'year' => today()->year, 'amount' => '2000']);
        $hoScope = $masters->createScope($this->policyAdmin, $budget->id, ['need_type' => 'HO', 'amount' => '1000']);
        $masters->createLine($this->policyAdmin, $hoScope->id, [
            'department_id' => $this->department->id, 'account_id' => $account->id, 'amount' => '300',
        ]);
        $scope = $masters->createScope($this->policyAdmin, $budget->id, [
            'need_type' => 'PROJECT', 'project_numbering_id' => $project->id, 'amount' => '1000',
        ]);
        $masters->createLine($this->policyAdmin, $scope->id, [
            'department_id' => $this->department->id, 'account_id' => $account->id, 'amount' => '800',
        ]);
        $line = $scope->lines()->sole();

        Livewire::actingAs($this->requester)->test(TravelSpd::class)->call('createDocument')
            ->assertSeeInOrder(['Perusahaan', 'Anggaran SPD'])
            ->assertSee('HO / Kantor Pusat')
            ->set('input.company', 'KPMOG')
            ->assertSet('input.finance_budget_line_id', (string) $hoScope->lines()->sole()->id)
            ->assertSee('Sisa IDR 300,00')
            ->set('input.project_numbering_id', (string) $project->id)
            ->assertSet('input.finance_budget_line_id', (string) $line->id)
            ->assertSee('Anggaran SPD')
            ->assertSee('Sisa IDR 800,00')
            ->set('input.project_numbering_id', '')
            ->assertSet('input.finance_budget_line_id', (string) $hoScope->lines()->sole()->id)
            ->assertSee('Sisa IDR 300,00')
            ->assertDontSee('SPD-PROJECT-BUDGET')->assertDontSee('Biaya perjalanan project');
    }

    private function input(string $company = 'APCA'): array
    {
        return [...app(TravelWorkflow::class)->defaults($this->requester, 'spd'),
            'number' => '001/SPD/11-2026', 'company' => $company, 'purpose' => 'Kunjungan pekerjaan pengujian',
            'project_numbering_id' => ProjectNumbering::where('code', 'COMP-19E001')->value('id'), 'job_code' => 'FORGED-JOB-CODE',
            'depart_at' => '2026-11-16T08:00', 'return_at' => '2026-11-18T17:00',
            'routes' => [['from' => 'Jakarta', 'to' => 'Lokasi Pengujian', 'mode' => 'Pesawat']],
            'lodgings' => [['name' => 'Penginapan QA', 'location' => 'Lokasi Pengujian', 'rooms' => '1', 'check_in' => '2026-11-16', 'check_out' => '2026-11-18']],
            'costs' => [['description' => 'Tunjangan Perjalanan Dinas', 'quantity' => '3', 'unit_cost' => '150000', 'notes' => 'Pengujian']],
            'approver_ids' => ['hr' => $this->hr->id, 'finance' => $this->finance->id, 'supervisor' => $this->supervisor->id]];
    }

    private function draft(string $company = 'APCA', array $files = []): TravelDocument
    {
        return app(TravelWorkflow::class)->save($this->requester, 'spd', null, 0, $this->input($company), $files);
    }

    private function approved(string $company = 'APCA'): TravelDocument
    {
        $workflow = app(TravelWorkflow::class);
        $document = $this->draft($company);
        $document = $workflow->submit($this->requester, $document->id, $document->version);
        foreach ([$this->hr, $this->finance, $this->supervisor] as $actor) {
            $document = $workflow->decide($actor, $document->id, $document->version, true);
        }

        return $document;
    }

    private function lpjInput(TravelDocument $spd): array
    {
        return [...app(TravelWorkflow::class)->defaults($this->requester, 'lpj'), 'number' => '', 'spd_id' => $spd->id,
            'approver_ids' => $spd->data['approver_ids'], 'advance_one' => '500000', 'advance_two' => '100000',
            'expenses' => [['date' => '2026-11-16', 'description' => 'Hotel dan perjalanan QA', 'hotel' => '250000', 'transport' => '100000', 'meals' => '50000', 'entertainment' => '0', 'other' => '0']]];
    }

    public function test_spd_is_saved_with_server_totals_snapshot_and_private_attachments(): void
    {
        $document = $this->draft(files: [UploadedFile::fake()->create('Rencana perjalanan.pdf', 10, 'application/pdf')]);
        $this->assertSame('draft', $document->status);
        $this->assertSame(45000000, $document->total_minor);
        $this->assertSame(3, $document->data['days']);
        $this->assertSame(2, $document->data['nights']);
        $this->assertSame(2, $document->data['lodgings'][0]['nights']);
        $this->assertSame('Karyawan QA', $document->employee_snapshot['name']);
        $file = $document->files()->sole();
        Storage::disk('local')->assertExists($file->path);
        $this->get(route('travel.file', $file))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($this->outsider)->get(route('travel.file', $file))->assertNotFound();
        $this->get(route('travel.print', $document))->assertNotFound();
    }

    public function test_approval_is_sequential_assigned_and_locks_the_approved_document(): void
    {
        $workflow = app(TravelWorkflow::class);
        $document = $this->draft();
        $document = $workflow->submit($this->requester, $document->id, $document->version);
        $this->assertTrue($workflow->canDecide($this->hr, $document));
        $this->assertFalse($workflow->canDecide($this->finance, $document));
        $this->assertFalse($workflow->canDecide($this->outsider, $document));
        foreach ([$this->finance, $this->outsider, $this->requester] as $actor) {
            try {
                $workflow->decide($actor, $document->id, $document->version, true);
                $this->fail('Unassigned or out-of-order approval accepted.');
            } catch (HttpException|ModelNotFoundException $exception) {
                $this->assertContains($exception instanceof HttpException ? $exception->getStatusCode() : 404, [403, 404]);
            }
        }
        foreach ([$this->hr, $this->finance, $this->supervisor] as $actor) {
            $document = $workflow->decide($actor, $document->id, $document->version, true);
        }
        $this->assertSame('approved', $document->status);
        $this->assertNotNull($document->approved_at);
        $this->assertSame(3, $document->currentApprovals()->where('status', 'approved')->count());
        $this->assertFalse(TravelAccess::editable($this->requester, $document));
        $this->assertSame(5, $document->events()->count());
        $this->expectException(HttpException::class);
        $workflow->save($this->requester, 'spd', $document->id, $document->version, $this->input());
    }

    public function test_lpj_requires_approved_spd_and_uses_its_identity_and_period(): void
    {
        $spd = $this->approved('KPMOG');
        $input = [...$this->lpjInput($spd), 'company' => 'APCA', 'employee_id' => $this->outsider->employee->id, 'purpose' => 'Forged', 'depart_at' => '1900-01-01T00:00'];
        $this->requester->employee->update(['name' => 'Nama master yang berubah']);
        $document = app(TravelWorkflow::class)->save($this->requester, 'lpj', null, 0, $input);
        $this->assertSame('KPMOG', $document->company);
        $this->assertSame($spd->employee_id, $document->employee_id);
        $this->assertSame('Karyawan QA', $document->employee_snapshot['name']);
        $this->assertSame($spd->data['purpose'], $document->data['purpose']);
        $this->assertSame($spd->data['depart_at'], $document->data['depart_at']);
        $this->assertSame(40000000, $document->total_minor);
        $this->assertSame(-20000000, $document->balance_minor);
        $this->assertSame(0, app(TravelWorkflow::class)->eligibleSpds($this->requester)->count());
        $document = app(TravelWorkflow::class)->submit($this->requester, $document->id, $document->version);
        foreach ([$this->hr, $this->finance, $this->supervisor] as $actor) {
            $document = app(TravelWorkflow::class)->decide($actor, $document->id, $document->version, true);
        }
        $this->assertSame('approved', $document->status);
        $this->assertSame(3, $document->currentApprovals()->count());
        $this->get(route('travel.print', $document))->assertOk()->assertSee('LAPORAN PERTANGGUNG JAWABAN (LPJ)')->assertSee('img/brands/kpmog.png', false)->assertDontSee('img/brands/apca.png', false);
    }

    public function test_lpj_cannot_use_draft_spd_or_dates_outside_trip(): void
    {
        $spd = $this->draft();
        try {
            app(TravelWorkflow::class)->save($this->requester, 'lpj', null, 0, $this->lpjInput($spd));
            $this->fail('Draft SPD accepted for LPJ.');
        } catch (ModelNotFoundException) {
            $this->assertSame(0, TravelDocument::where('kind', 'lpj')->count());
        }
        $spd = app(TravelWorkflow::class)->submit($this->requester, $spd->id, $spd->version);
        foreach ([$this->hr, $this->finance, $this->supervisor] as $actor) {
            $spd = app(TravelWorkflow::class)->decide($actor, $spd->id, $spd->version, true);
        }
        $input = $this->lpjInput($spd);
        $input['expenses'][0]['date'] = '2026-11-30';
        try {
            app(TravelWorkflow::class)->save($this->requester, 'lpj', null, 0, $input);
            $this->fail('Out-of-period expense accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('expenses.0.date', $exception->errors());
        }
    }

    public function test_returned_document_keeps_previous_round_and_requires_fresh_approvals(): void
    {
        $workflow = app(TravelWorkflow::class);
        $document = $this->draft();
        $document = $workflow->submit($this->requester, $document->id, $document->version);
        $document = $workflow->decide($this->hr, $document->id, $document->version, true);
        $document = $workflow->decide($this->finance, $document->id, $document->version, false, 'Lengkapi rincian biaya.');
        $this->assertSame('returned', $document->status);
        $before = $document->approvals()->get()->map->getRawOriginal()->all();
        $document = $workflow->save($this->requester, 'spd', $document->id, $document->version, $this->input());
        $this->assertNull($document->prepared_signature);
        $this->assertNull($document->submitted_at);
        $this->assertNotNull($document->events()->where('action', 'draft_updated')->latest('id')->first()->details['before']['prepared_signature']);
        $document = $workflow->submit($this->requester, $document->id, $document->version);
        $this->assertSame(2, $document->approval_round);
        $this->assertTrue($workflow->canDecide($this->hr, $document));
        $this->assertFalse($workflow->canDecide($this->finance, $document));
        $this->assertSame(6, $document->approvals()->count());
        $this->assertSame($before, $document->approvals()->where('round', 1)->get()->map->getRawOriginal()->all());
    }

    public function test_manual_signer_ids_cannot_override_master_and_invalid_master_is_rejected(): void
    {
        $workflow = app(TravelWorkflow::class);
        foreach ([['hr' => $this->finance->id], ['finance' => $this->hr->id], ['supervisor' => $this->outsider->id]] as $index => $override) {
            $input = $this->input();
            $input['number'] = sprintf('%03d/SPD/11-2026', $index + 2);
            $input['approver_ids'] = [...$input['approver_ids'], ...$override];
            $document = $workflow->save($this->requester, 'spd', null, 0, $input);
            $document = $workflow->submit($this->requester, $document->id, $document->version);
            $this->assertSame([$this->hr->id, $this->finance->id, $this->supervisor->id], $document->currentApprovals()->pluck('user_id')->all());
        }
        $policy = TravelApprovalPolicy::where('company', 'APCA')->where('travel_type', 'PROJECT')->sole();
        $policy->update(['supervisor_id' => $this->outsider->id]);
        $input = [...$this->input(), 'number' => '089/SPD/11-2026'];
        $draft = $workflow->save($this->requester, 'spd', null, 0, $input);
        try {
            $workflow->submit($this->requester, $draft->id, $draft->version);
            $this->fail('Wrong supervisor from master accepted.');
        } catch (ValidationException) {
            $this->assertSame(0, $draft->approvals()->count());
        }
        $this->requester->directPermissions()->attach(Permission::where('code', 'travel.approve')->sole());
        $policy->update(['supervisor_id' => null, 'finance_id' => $this->requester->id]);
        $input = $this->input();
        $input['number'] = '090/SPD/11-2026';
        $input['approver_ids']['finance'] = $this->requester->id;
        $document = $workflow->save($this->requester, 'spd', null, 0, $input);
        $this->expectException(ValidationException::class);
        $workflow->submit($this->requester, $document->id, $document->version);
    }

    public function test_ho_uses_finance_while_project_uses_project_manager_and_lpj_inherits_the_route(): void
    {
        $projectManager = $this->actor($this->department, 'Manager Project QA', ['travel.approve'], 'manager');
        TravelApprovalPolicy::where('company', 'APCA')->where('travel_type', 'PROJECT')->sole()->update(['finance_id' => $projectManager->id]);
        $workflow = app(TravelWorkflow::class);

        $ho = $workflow->save($this->requester, 'spd', null, 0, [...$this->input(), 'number' => '051/SPD/11-2026', 'project_numbering_id' => '']);
        $this->assertSame('HO', $ho->data['travel_type']);
        $ho = $workflow->submit($this->requester, $ho->id, $ho->version);
        $this->assertSame([$this->hr->id, $this->finance->id, $this->supervisor->id], $ho->currentApprovals()->pluck('user_id')->all());

        $project = $workflow->save($this->requester, 'spd', null, 0, [...$this->input(), 'number' => '052/SPD/11-2026']);
        $this->assertSame('PROJECT', $project->data['travel_type']);
        $project = $workflow->submit($this->requester, $project->id, $project->version);
        $this->assertSame([$this->hr->id, $projectManager->id, $this->supervisor->id], $project->currentApprovals()->pluck('user_id')->all());
        $this->get(route('travel.print', $project))->assertOk()->assertSee('Manager Project')->assertDontSee('>Finance<', false);
        foreach ([$this->hr, $projectManager, $this->supervisor] as $actor) {
            $project = $workflow->decide($actor, $project->id, $project->version, true);
        }

        $lpj = $workflow->save($this->requester, 'lpj', null, 0, $this->lpjInput($project));
        $this->assertSame('PROJECT', $lpj->data['travel_type']);
        $lpj = $workflow->submit($this->requester, $lpj->id, $lpj->version);
        $this->assertSame([$this->hr->id, $projectManager->id, $this->supervisor->id], $lpj->currentApprovals()->pluck('user_id')->all());
    }

    public function test_department_viewer_cannot_edit_and_stale_saves_do_not_overwrite(): void
    {
        $document = $this->draft();
        $viewer = $this->actor($this->department, 'Pembaca QA', ['travel.spd.view']);
        $this->assertSame($document->id, TravelAccess::query($viewer, 'spd')->sole()->id);
        $this->assertFalse(TravelAccess::editable($viewer, $document));
        $this->assertSame(0, TravelAccess::query($this->outsider, 'spd')->count());
        try {
            app(TravelWorkflow::class)->save($viewer, 'spd', $document->id, $document->version, $this->input());
            $this->fail('Read-only user edited a document.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        app(TravelWorkflow::class)->save($this->requester, 'spd', $document->id, $document->version, [...$this->input(), 'notes' => 'Versi terbaru']);
        try {
            app(TravelWorkflow::class)->save($this->requester, 'spd', $document->id, $document->version, $this->input());
            $this->fail('Stale data overwrote the current draft.');
        } catch (ValidationException) {
            $this->assertSame('Versi terbaru', $document->fresh()->data['notes']);
        }
        $this->requester->employee->update(['is_active' => false]);
        $this->requester->unsetRelation('employee');
        $this->assertFalse(TravelSpd::canAccess());
        $this->assertSame(0, TravelAccess::query($this->requester)->count());
    }

    public function test_forms_follow_template_and_livewire_saves_and_submits_without_scm_permissions(): void
    {
        $page = Livewire::test(TravelSpd::class)->assertSee('Belum ada SPD')->call('createDocument')
            ->assertSee('FORMULIR PERMOHONAN PERJALANAN DINAS')->assertSee('PERINCIAN DANA PERJALANAN')
            ->set('input', $this->input('KPMOG'))->call('saveDraft')->assertHasNoErrors();
        $id = $page->get('selected');
        $this->assertSame(1, TravelDocument::count());
        $page->call('submitDocument')->assertHasNoErrors()->assertSee('Menunggu persetujuan');
        $document = TravelDocument::findOrFail($id);
        $this->get(route('travel.print', $document))->assertOk()->assertSee('PT. KPM OIL &amp; GAS', false)->assertSee('belum disetujui seluruh pemeriksa.');
        $this->assertTrue(Access::signs($this->requester));
        $this->assertFalse(Access::has($this->requester, 'scm.request'));
        foreach ([$this->hr, $this->finance, $this->supervisor] as $actor) {
            Livewire::actingAs($actor)->test(TravelSpd::class, ['selected' => $id])->call('approveDocument')->assertHasNoErrors();
        }
        Livewire::actingAs($this->requester)->test(TravelLpj::class)->call('createDocument')->set('input.spd_id', (string) $id)
            ->assertSet('input.company', 'KPMOG')->assertSet('input.advance_one', '450000,00')->assertSee('LAPORAN PERTANGGUNG JAWABAN (LPJ)')->assertSee('Uang Muka 1')
            ->set('input.expenses', $this->lpjInput($document)['expenses'])
            ->set('input.advance_one', '400000')
            ->call('saveDraft')->assertHasNoErrors();
        $this->assertSame(2, TravelDocument::count());
    }

    public function test_duplicate_lpj_and_invalid_attachment_do_not_create_documents(): void
    {
        $spd = $this->approved();
        $workflow = app(TravelWorkflow::class);
        $workflow->save($this->requester, 'lpj', null, 0, $this->lpjInput($spd));
        try {
            $workflow->save($this->requester, 'lpj', null, 0, [...$this->lpjInput($spd), 'number' => '']);
            $this->fail('A second LPJ for the same SPD was created.');
        } catch (ModelNotFoundException) {
            $this->assertSame(1, TravelDocument::where('kind', 'lpj')->count());
        }
        try {
            $workflow->save($this->requester, 'spd', null, 0, [...$this->input(), 'number' => '099/SPD/11-2026'], [UploadedFile::fake()->create('script.php', 1, 'text/x-php')]);
            $this->fail('Executable attachment was accepted.');
        } catch (ValidationException) {
            $this->assertSame(2, TravelDocument::count());
            $this->assertSame([], Storage::disk('local')->allFiles());
        }
    }

    public function test_invalid_money_remains_editable_and_returns_a_field_error(): void
    {
        Livewire::test(TravelSpd::class)->call('createDocument')->set('input', $this->input())
            ->set('input.costs.0.unit_cost', '1.000.000')->call('saveDraft')->assertHasErrors(['costs.0.unit_cost']);
        $this->assertSame(0, TravelDocument::count());
    }

    public function test_job_code_is_taken_from_project_master_without_project_name_or_pr_number(): void
    {
        $project = ProjectNumbering::withoutEvents(fn () => ProjectNumbering::create(['code' => 'BMTH-BBM/25/SPC/003',
            'name' => 'PROJECT TITLE MUST NOT BE INCLUDED', 'is_active' => true, 'version' => 1]));
        $input = [...$this->input('KPMOG'), 'project_numbering_id' => $project->id, 'job_code' => 'FORGED/PR/NUMBER'];
        $workflow = app(TravelWorkflow::class);
        $spd = $workflow->save($this->requester, 'spd', null, 0, $input);
        $this->assertSame($project->id, $spd->project_numbering_id);
        $this->assertSame('BMTH-BBM/25/SPC/003', $spd->data['job_code']);
        $this->assertSame($project->id, $spd->projectNumbering->id);
        $spd = $workflow->submit($this->requester, $spd->id, $spd->version);
        foreach ([$this->hr, $this->finance, $this->supervisor] as $actor) {
            $spd = $workflow->decide($actor, $spd->id, $spd->version, true);
        }
        $project->update(['code' => 'UPDATED/26/SPC/004', 'is_active' => false]);
        $lpj = $workflow->save($this->requester, 'lpj', null, 0, [...$this->lpjInput($spd), 'project_numbering_id' => 999999, 'job_code' => 'FORGED']);
        $this->assertSame($project->id, $lpj->project_numbering_id);
        $this->assertSame('BMTH-BBM/25/SPC/003', $lpj->data['job_code']);
        $this->get(route('travel.print', $lpj))->assertOk()->assertSee('BMTH-BBM/25/SPC/003')
            ->assertDontSee('PROJECT TITLE MUST NOT BE INCLUDED')->assertDontSee('FORGED')->assertDontSee('UPDATED/26/SPC/004');
        Livewire::test(TravelSpd::class)->call('createDocument')->assertSee('COMP-19E001')
            ->assertDontSee('UPDATED/26/SPC/004')->assertDontSee('PROJECT TITLE MUST NOT BE INCLUDED');
    }

    public function test_unavailable_project_code_is_rejected_and_free_text_is_not_trusted(): void
    {
        $inactive = ProjectNumbering::withoutEvents(fn () => ProjectNumbering::create(['code' => 'INACTIVE', 'name' => 'Inactive QA', 'is_active' => false]));
        foreach ([$inactive->id, 999999] as $id) {
            try {
                app(TravelWorkflow::class)->save($this->requester, 'spd', null, 0, [...$this->input(), 'project_numbering_id' => $id]);
                $this->fail('Unavailable project master accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('project_numbering_id', $exception->errors());
            }
        }
        $draft = app(TravelWorkflow::class)->save($this->requester, 'spd', null, 0, [...$this->input(), 'project_numbering_id' => '', 'job_code' => 'FORGED']);
        $this->assertNull($draft->project_numbering_id);
        $this->assertSame('', $draft->data['job_code']);
    }

    public function test_spd_number_keeps_leading_zeroes_and_validates_the_requested_format(): void
    {
        $workflow = app(TravelWorkflow::class);
        foreach (['SPD-QA-001', '1/SPD/01-2026', '001/LPJ/01-2026', '001/SPD/00-2026', '001/SPD/13-2026', '000/SPD/01-2026'] as $number) {
            try {
                $workflow->save($this->requester, 'spd', null, 0, [...$this->input(), 'number' => $number]);
                $this->fail('Invalid SPD number accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('number', $exception->errors());
            }
        }
        $draft = $workflow->save($this->requester, 'spd', null, 0, [...$this->input(), 'number' => ' 001/spd/01-2026 ']);
        $this->assertSame('001/SPD/01-2026', $draft->number);
        $this->assertSame('001/spd/01-2026', $draft->number_key);
        $this->get(route('travel.print', $draft))->assertOk()->assertSee('001/SPD/01-2026');
    }

    public function test_lpj_number_is_automatic_and_keeps_existing_legacy_numbers(): void
    {
        $workflow = app(TravelWorkflow::class);
        $spd = $this->approved();
        $lpj = $workflow->save($this->requester, 'lpj', null, 0, [...$this->lpjInput($spd), 'issued_on' => '2026-01-16']);
        $this->assertSame('001/LPJ/01-2026', $lpj->number);
        $lpj = $workflow->save($this->requester, 'lpj', $lpj->id, $lpj->version, [...$workflow->formData($lpj), 'issued_on' => '2026-02-16']);
        $this->assertSame('002/LPJ/02-2026', $lpj->number);
        $lpj->update(['number' => 'LPJ-LAMA', 'number_key' => 'lpj-lama']);
        $lpj = $workflow->save($this->requester, 'lpj', $lpj->id, $lpj->version, $workflow->formData($lpj));
        $this->assertSame('LPJ-LAMA', $lpj->number);
    }

    public function test_spd_auto_number_runs_across_months_and_separates_company_and_year(): void
    {
        $workflow = app(TravelWorkflow::class);
        $base = [...$this->input('KPMOG'), 'number' => ''];
        $first = $workflow->save($this->requester, 'spd', null, 0, [...$base, 'issued_on' => '2026-01-16']);
        $second = $workflow->save($this->requester, 'spd', null, 0, [...$base, 'issued_on' => '2026-02-16']);
        $apca = $workflow->save($this->requester, 'spd', null, 0, [...$base, 'company' => 'APCA', 'issued_on' => '2026-02-16']);
        $nextYear = $workflow->save($this->requester, 'spd', null, 0, [...$base, 'issued_on' => '2027-01-16']);
        $this->assertSame('001/SPD/01-2026', $first->number);
        $this->assertSame('002/SPD/02-2026', $second->number);
        $this->assertSame('001/SPD/02-2026', $apca->number);
        $this->assertSame('001/SPD/01-2027', $nextYear->number);
        $workflow->save($this->requester, 'spd', null, 0, [...$base, 'number' => '035/SPD/02-2026', 'issued_on' => '2026-02-16']);
        $next = $workflow->save($this->requester, 'spd', null, 0, [...$base, 'issued_on' => '2026-03-16']);
        $this->assertSame('036/SPD/03-2026', $next->number);
        $first = $workflow->save($this->requester, 'spd', $first->id, $first->version, [...$workflow->formData($first), 'notes' => 'Tambahan catatan', 'number' => '']);
        $this->assertSame('001/SPD/01-2026', $first->number);
        $this->assertSame(36, (int) DB::table('travel_spd_number_sequences')->where('company', 'KPMOG')->where('year', 2026)->value('last_number'));
    }

    public function test_failed_save_does_not_consume_a_number_and_draft_period_change_renumbers(): void
    {
        $workflow = app(TravelWorkflow::class);
        $input = [...$this->input('KPMOG'), 'number' => '', 'issued_on' => '2026-01-16'];
        $invalid = $input;
        $invalid['costs'][0]['unit_cost'] = 'invalid';
        try {
            $workflow->save($this->requester, 'spd', null, 0, $invalid);
            $this->fail('Invalid amount accepted.');
        } catch (ValidationException) {
            $this->assertSame(0, DB::table('travel_spd_number_sequences')->count());
        }
        $draft = $workflow->save($this->requester, 'spd', null, 0, $input);
        $this->assertSame('001/SPD/01-2026', $draft->number);
        $draft = $workflow->save($this->requester, 'spd', $draft->id, $draft->version, [...$workflow->formData($draft), 'issued_on' => '2026-02-16']);
        $this->assertSame('002/SPD/02-2026', $draft->number);
        $draft = $workflow->save($this->requester, 'spd', $draft->id, $draft->version, [...$workflow->formData($draft), 'company' => 'APCA']);
        $this->assertSame('001/SPD/02-2026', $draft->number);
    }

    public function test_legacy_draft_identity_is_not_rewritten_when_editing_other_fields(): void
    {
        $workflow = app(TravelWorkflow::class);
        $draft = $this->draft();
        $data = $draft->data;
        $data['job_code'] = 'KODE-LAMA';
        $draft->update(['project_numbering_id' => null, 'data' => $data, 'number' => 'nomor-lama', 'number_key' => 'nomor-lama']);
        $before = $draft->getRawOriginal();
        $draft = $workflow->save($this->requester, 'spd', $draft->id, $draft->version,
            [...$workflow->formData($draft), 'notes' => 'Melengkapi catatan']);
        $this->assertSame('nomor-lama', $draft->number);
        $this->assertSame('KODE-LAMA', $draft->data['job_code']);
        $this->assertSame($before['employee_id'], $draft->getRawOriginal('employee_id'));
    }

    public function test_print_forms_keep_layout_sections_and_only_change_company_branding(): void
    {
        foreach (['APCA', 'KPMOG'] as $company) {
            $spd = $this->approved($company);
            $lpj = app(TravelWorkflow::class)->save($this->requester, 'lpj', null, 0, $this->lpjInput($spd));
            $lpj = app(TravelWorkflow::class)->submit($this->requester, $lpj->id, $lpj->version);
            foreach ([$this->hr, $this->finance, $this->supervisor] as $actor) {
                $lpj = app(TravelWorkflow::class)->decide($actor, $lpj->id, $lpj->version, true);
            }
            foreach ([$spd, $lpj] as $document) {
                $response = $this->get(route('travel.print', $document))->assertOk()
                    ->assertSee('travel-sheet-'.$document->kind)->assertSee('img/brands/'.strtolower($company).'.png', false)
                    ->assertSee('Karyawan QA')->assertSee('HR Manager QA')->assertSee('Finance QA')->assertSee('Atasan QA');
                $this->assertSame(4, substr_count($response->getContent(), 'travel-signature-space'));
                if ($directory = getenv('TRAVEL_REVIEW_DIRECTORY')) {
                    if (! is_dir($directory)) {
                        mkdir($directory, 0700, true);
                    }
                    file_put_contents($directory.'/'.$document->kind.'-'.strtolower($company).'.html', $response->getContent());
                }
            }
        }
    }

    public function test_spd_identity_and_project_fields_share_alignment_tracks(): void
    {
        $spd = $this->approved('KPMOG');

        $content = $this->get(route('travel.print', $spd))->assertOk()->getContent();

        $this->assertStringContainsString('class="travel-spd-field travel-spd-person"', $content);
        $this->assertStringContainsString('class="travel-spd-field travel-spd-project"', $content);
        $this->assertStringContainsString('<label>Tgl Pengajuan</label><b>:</b>', $content);
        $this->assertStringContainsString('<label>Lampiran</label><b>:</b>', $content);

        $css = file_get_contents(public_path('css/travel.css'));
        $this->assertNotFalse($css);
        $this->assertStringContainsString('--travel-spd-label:15rem', $css);
        $this->assertStringContainsString('.travel-applied{display:grid;grid-template-columns:minmax(0,1fr);align-content:start;gap:5px}', $css);
        $this->assertStringContainsString('.travel-spd-identity>div.travel-spd-person{grid-template-columns:minmax(0,var(--travel-spd-label)) var(--travel-spd-colon) minmax(0,1fr)}', $css);
        $this->assertStringContainsString('.travel-applied .travel-spd-field,.travel-spd-project{grid-template-columns:minmax(0,1fr) var(--travel-spd-colon) minmax(0,1fr);align-items:center}', $css);
    }

    public function test_sample_spd_with_units_tba_multiple_routes_and_empty_lodging_round_trips_to_print(): void
    {
        $workflow = app(TravelWorkflow::class);
        foreach (['APCA', 'KPMOG'] as $company) {
            $input = [...$this->input($company), 'number' => '', 'issued_on' => '2026-08-12', 'applied_on' => '2026-08-12',
                'depart_date' => '2026-08-13', 'return_date' => '2026-09-01', 'depart_time_tba' => true, 'return_time_tba' => true,
                'lodgings' => [], 'purpose' => 'Shutdown plant dan top overhaul unit QA',
                'routes' => [
                    ['from' => 'Rumah', 'to' => 'Bandara Soekarno Hatta', 'mode' => 'Taksi'],
                    ['from' => 'Jakarta', 'to' => 'Palembang', 'mode' => 'Pesawat'],
                    ['from' => 'Palembang', 'to' => 'Sungai Lilin (Mess)', 'mode' => 'Travel'],
                    ['from' => 'Sungai Lilin (Mess)', 'to' => 'Palembang', 'mode' => 'Travel'],
                    ['from' => 'Palembang', 'to' => 'Jakarta', 'mode' => 'Pesawat'],
                    ['from' => 'Bandara Soekarno Hatta', 'to' => 'Rumah', 'mode' => 'Taksi'],
                ], 'costs' => [
                    ['description' => 'Taksi dan tol, rumah ke bandara', 'quantity' => '2', 'unit' => 'PP', 'unit_cost' => '230000'],
                    ['description' => 'Tunjangan lapangan', 'quantity' => '20', 'unit' => 'Hari', 'unit_cost' => '255000'],
                    ['description' => 'Travel', 'quantity' => '2', 'unit' => 'PP', 'unit_cost' => '200000'],
                    ['description' => 'Kontingensi material', 'quantity' => '1', 'unit' => 'Lot', 'unit_cost' => '2000000'],
                ], 'notes' => 'Dana ditransfer dua hari sebelum keberangkatan.'];
            $document = $workflow->save($this->requester, 'spd', null, 0, $input);
            $this->assertSame(796000000, $document->total_minor);
            $this->assertSame('2026-08-13', $document->data['depart_at']);
            $this->assertSame('2026-09-01', $document->data['return_at']);
            $this->assertSame(20, $document->data['days']);
            $this->assertSame(19, $document->data['nights']);
            $this->assertSame([], $document->data['lodgings']);
            $document = $workflow->save($this->requester, 'spd', $document->id, $document->version, $workflow->formData($document));
            $this->assertSame(['PP', 'Hari', 'PP', 'Lot'], array_column($document->data['costs'], 'unit'));
            $document = $workflow->submit($this->requester, $document->id, $document->version);
            foreach ([$this->hr, $this->finance, $this->supervisor] as $actor) {
                $document = $workflow->decide($actor, $document->id, $document->version, true);
            }
            $response = $this->get(route('travel.print', $document))->assertOk()->assertSee('20 Hari')->assertSee('2 PP')->assertSee('1 Lot')
                ->assertSee('7.960.000,00')->assertSee('Jam TBA')->assertSee('13/08/2026')->assertSee('01/09/2026')->assertSee('Sungai Lilin (Mess)');
            $this->assertSame(4, substr_count($response->getContent(), 'travel-signature-space'));
            $this->assertSame(11, substr_count($response->getContent(), 'class="travel-empty-cost"'));
            if ($directory = getenv('TRAVEL_REVIEW_DIRECTORY')) {
                if (! is_dir($directory)) {
                    mkdir($directory, 0700, true);
                }
                file_put_contents($directory.'/spd-sample-'.strtolower($company).'.html', $response->getContent());
            }
            $lpj = $workflow->save($this->requester, 'lpj', null, 0, [...$this->lpjInput($document), 'number' => '',
                'depart_date' => '1900-01-01', 'depart_time_tba' => false,
                'expenses' => [['date' => '2026-09-01', 'description' => 'Biaya kembali', 'transport' => '100000']]]);
            $this->assertSame('2026-08-13', $lpj->data['depart_at']);
            $this->assertSame('2026-09-01', $lpj->data['return_at']);
            $this->assertTrue($lpj->data['depart_time_tba']);
            $this->assertSame(10000000, $lpj->total_minor);
            try {
                $workflow->save($this->requester, 'lpj', $lpj->id, $lpj->version, [...$workflow->formData($lpj),
                    'expenses' => [['date' => '2026-09-02', 'description' => 'Di luar periode', 'transport' => '100000']]]);
                $this->fail('Expense after the return date accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('expenses.0.date', $exception->errors());
            }
        }
    }

    public function test_split_date_time_controls_update_duration_and_save_known_and_tba_times(): void
    {
        $page = Livewire::test(TravelSpd::class)->call('createDocument')->set('input', $this->input())
            ->set('input.lodgings', [])
            ->assertSee('wire:model.live="input.depart_date"', false)->assertSee('Jam TBA')
            ->assertSee('wire:model.blur="input.costs.0.unit"', false)
            ->set('input.depart_date', '2026-08-13')->set('input.return_date', '2026-09-01')
            ->assertSet('input.days', 20)->assertSet('input.nights', 19)
            ->set('input.depart_time_tba', false)->set('input.depart_time', '06:30')
            ->set('input.return_time_tba', true)->call('saveDraft')->assertHasNoErrors();
        $document = TravelDocument::findOrFail($page->get('selected'));
        $this->assertSame('2026-08-13T06:30', $document->data['depart_at']);
        $this->assertSame('2026-09-01', $document->data['return_at']);
        $page->set('input.return_time_tba', false)->set('input.return_time', '')->call('saveDraft')->assertHasErrors(['return_time']);
        $this->assertSame(1, $document->fresh()->version);
        $page->set('input.return_time', '18:00')->call('saveDraft')->assertHasNoErrors();
        $this->assertSame('2026-09-01T18:00', $document->fresh()->data['return_at']);
    }

    public function test_legacy_times_and_costs_without_units_are_not_rewritten_by_print_and_remain_editable(): void
    {
        $workflow = app(TravelWorkflow::class);
        $document = $this->draft();
        $data = $document->data;
        foreach ($data['costs'] as &$row) {
            unset($row['unit']);
        }
        unset($row, $data['depart_time_tba'], $data['return_time_tba']);
        $document->update(['data' => $data]);
        $before = $document->getRawOriginal();
        $this->get(route('travel.print', $document))->assertOk()->assertSee('Jam 08:00')->assertSee('Jam 17:00');
        $this->assertSame($before, $document->fresh()->getRawOriginal());
        $fields = $workflow->formData($document);
        $this->assertSame('', $fields['costs'][0]['unit']);
        $this->assertFalse($fields['depart_time_tba']);
        $document = $workflow->save($this->requester, 'spd', $document->id, $document->version, [...$fields, 'notes' => 'Catatan baru']);
        $this->assertSame('2026-11-16T08:00', $document->data['depart_at']);
        $this->assertSame('2026-11-18T17:00', $document->data['return_at']);
    }

    public function test_duration_result_guides_date_selection_and_updates_without_waiting_for_times(): void
    {
        $page = Livewire::test(TravelSpd::class)->call('createDocument')
            ->assertSee('Belum dihitung')->assertSee('Pilih tanggal berangkat dan tanggal kembali untuk menghitung durasi.')
            ->assertSee('<output aria-label="Total durasi perjalanan">', false)
            ->set('input.depart_time_tba', false)->set('input.return_time_tba', false)
            ->set('input.depart_date', '2026-08-13')->assertSee('Belum dihitung')
            ->set('input.return_date', '2026-09-01')->assertSet('input.days', 20)->assertSet('input.nights', 19)
            ->assertSee('20 hari / 19 malam')->assertSee('Dihitung otomatis dari tanggal berangkat dan kembali.')
            ->set('input.return_date', '2026-08-13')->assertSee('1 hari / 0 malam')
            ->set('input.return_date', '2026-08-12')->assertSee('Belum dihitung')
            ->assertSee('Tanggal kembali tidak boleh sebelum tanggal berangkat.');
        $this->assertArrayNotHasKey('days', $page->get('input'));
        $page->set('input.return_date', '2026-09-01')->assertSee('20 hari / 19 malam')
            ->set('input.depart_date', '')->assertSee('Belum dihitung');
        $this->assertArrayNotHasKey('days', $page->get('input'));
        $page->set('input', [...$this->input(), 'lodgings' => [], 'days' => 999, 'nights' => 998,
            'depart_date' => '2026-08-13', 'return_date' => '2026-09-01', 'depart_time_tba' => false,
            'return_time_tba' => false, 'depart_time' => '', 'return_time' => ''])
            ->assertSee('20 hari / 19 malam')->call('saveDraft')->assertHasErrors(['depart_time']);
        $page->set('input.depart_time', '08:00')->set('input.return_time', '17:00')->call('saveDraft')->assertHasNoErrors();
        $document = TravelDocument::findOrFail($page->get('selected'));
        $this->assertSame(20, $document->data['days']);
        $this->assertSame(19, $document->data['nights']);
    }

    public function test_estimate_table_matches_numbered_items_and_merged_notes_while_preserving_old_item_notes(): void
    {
        $input = [...$this->input(), 'cost_notes' => "Catatan seluruh rincian\nTransfer sebelum keberangkatan.", 'costs' => [
            ['description' => 'Taksi dan tol', 'quantity' => '2', 'unit' => 'PP', 'unit_cost' => '230000', 'notes' => 'Bukti taksi tetap disimpan'],
            ['description' => 'Tunjangan lapangan', 'quantity' => '20', 'unit' => 'Hari', 'unit_cost' => '255000', 'notes' => 'Catatan tunjangan'],
        ]];
        $page = Livewire::test(TravelSpd::class)->call('createDocument')->set('input', $input)
            ->assertSee('wire:model.blur="input.cost_notes"', false)->assertSee('Catatan per item tersimpan')
            ->assertSee('wire:model.blur="input.costs.0.notes"', false)
            ->call('saveDraft')->assertHasNoErrors();
        $workflow = app(TravelWorkflow::class);
        $document = TravelDocument::findOrFail($page->get('selected'));
        $this->assertSame($input['cost_notes'], $document->data['cost_notes']);
        $this->assertSame('Bukti taksi tetap disimpan', $document->data['costs'][0]['notes']);
        $response = $this->get(route('travel.print', $document))->assertOk()
            ->assertSee('Transfer sebelum keberangkatan.')->assertSee('1. Bukti taksi tetap disimpan')->assertSee('2. Catatan tunjangan');
        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML($response->getContent(), LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($dom);
        $table = $xpath->query('//table[contains(@class,"travel-estimate-reference")]')->item(0);
        $this->assertNotNull($table);
        $this->assertSame('2', $xpath->query('thead/tr/th[1]', $table)->item(0)->getAttribute('colspan'));
        $this->assertSame(6, $xpath->query('colgroup/col', $table)->length);
        $this->assertSame(15, $xpath->query('tbody/tr', $table)->length);
        $this->assertSame(1, $xpath->query('tbody/tr/td[contains(@class,"travel-estimate-notes")]', $table)->length);
        $this->assertSame('15', $xpath->query('tbody/tr[1]/td[6]', $table)->item(0)->getAttribute('rowspan'));
        $this->assertSame('1', trim($xpath->query('tbody/tr[1]/td[1]', $table)->item(0)->textContent));
        $this->assertSame('2', trim($xpath->query('tbody/tr[2]/td[1]', $table)->item(0)->textContent));
        $this->assertSame('4', $xpath->query('tfoot/tr/th', $table)->item(0)->getAttribute('colspan'));
        $this->assertStringContainsString('5.560.000,00', $xpath->query('tfoot/tr/td[1]', $table)->item(0)->textContent);
        $this->assertStringNotContainsString('Hapus', $table->textContent);
        $page->call('removeRow', 'costs', 0)->assertSee('Item 1: Tunjangan lapangan')->call('saveDraft')->assertHasNoErrors();
        $document = $document->fresh();
        $this->assertSame(510000000, $document->total_minor);
        $this->assertSame('Catatan tunjangan', $document->data['costs'][0]['notes']);
        $this->assertSame($input['cost_notes'], $document->data['cost_notes']);
        $legacyClient = $workflow->formData($document);
        unset($legacyClient['cost_notes']);
        $document = $workflow->save($this->requester, 'spd', $document->id, $document->version, $legacyClient);
        $this->assertSame($input['cost_notes'], $document->data['cost_notes']);
        $page->call('openDocument', $document->id)->set('input.cost_notes', str_repeat('x', 5001))
            ->call('saveDraft')->assertHasErrors(['cost_notes']);
        $this->assertSame($input['cost_notes'], $document->fresh()->data['cost_notes']);
    }

    public function test_estimate_notes_remain_accessible_with_no_items_and_expand_when_more_than_fifteen_items(): void
    {
        $workflow = app(TravelWorkflow::class);
        $page = Livewire::test(TravelSpd::class)->call('createDocument')->set('input', [...$this->input(), 'costs' => []])
            ->assertSee('wire:model.blur="input.cost_notes"', false)->set('input.cost_notes', '<script>notes</script>')
            ->call('addRow', 'costs')->set('input.costs.0.description', 'Rincian tambahan')->set('input.costs.0.unit_cost', '100')
            ->call('saveDraft')->assertHasNoErrors();
        $document = TravelDocument::findOrFail($page->get('selected'));
        $this->get(route('travel.print', $document))->assertOk()->assertSee('<script>notes</script>')
            ->assertDontSee('<script>notes</script>', false);
        $input = $workflow->formData($document);
        $input['costs'] = array_map(fn ($index) => ['description' => 'Item '.$index, 'quantity' => '1', 'unit' => 'Lot', 'unit_cost' => '100', 'notes' => ''], range(1, 18));
        $document = $workflow->save($this->requester, 'spd', $document->id, $document->version, $input);
        $this->assertSame(180000, $document->total_minor);
        $this->get(route('travel.print', $document))->assertOk()->assertSee('rowspan="18"', false)->assertSee('Item 18')
            ->assertDontSee('class="travel-empty-cost"', false);
    }
}
