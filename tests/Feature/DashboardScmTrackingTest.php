<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Scm\Procurement;
use App\Models\Scm\ProjectNumbering;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardScmTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_tracks_the_current_approval_and_return_to_the_requesting_department(): void
    {
        $requester = $this->actor('Engineering', 'scm.request');
        $oldApprover = $this->actor('Operations');
        $currentApprover = $this->actor('Finance');
        $request = $this->request($requester, 'PR-APPROVAL', 'approval', ['approval_round' => 2]);
        $request->approvals()->createMany([
            ['round' => 1, 'position' => 1, 'role' => 'acknowledged', 'user_id' => $oldApprover->id, 'is_required' => true, 'status' => 'returned'],
            ['round' => 2, 'position' => 1, 'role' => 'acknowledged', 'user_id' => $currentApprover->id, 'is_required' => true, 'status' => 'pending'],
            ['round' => 2, 'position' => 2, 'role' => 'approved', 'user_id' => $oldApprover->id, 'is_required' => true, 'status' => 'pending'],
        ]);
        $this->request($oldApprover, 'PR-OTHER', 'submitted');
        $this->request($requester, 'PR-UNSUBMITTED', 'draft');

        $this->actingAs($requester)->get('/panel')->assertOk()
            ->assertSeeInOrder(['PR-APPROVAL', 'Posisi saat ini', 'Finance', 'Tahap proses', 'Menunggu tanda tangan (Acknowledged by)'])
            ->assertDontSeeText('PR-OTHER')
            ->assertDontSeeText('PR-UNSUBMITTED');
        $this->get('/panel/permintaan/pr?permintaan='.$request->id)->assertOk()->assertSeeText('PR-APPROVAL');

        $request->update(['status' => 'draft']);
        $this->get('/panel')->assertOk()->assertSeeInOrder(['PR-APPROVAL', 'Engineering', 'Dikembalikan untuk revisi']);
    }

    public function test_processor_can_page_through_only_their_project_scope(): void
    {
        $processor = $this->actor('Engineering', 'scm.process-kpmog');
        $requester = $this->actor('Finance', 'scm.request');
        $project = ['department_id' => $processor->employee->department_id, 'company' => 'KPMOG', 'need_type' => 'PROJECT'];

        for ($number = 1; $number <= 6; $number++) {
            $this->request($requester, 'PR-PROJECT-'.$number, 'quotations', $project);
        }
        $this->request($requester, 'PR-PENDING', 'approval', $project);
        $this->request($requester, 'PR-HO', 'submitted', [...$project, 'need_type' => 'HO']);
        $this->request($requester, 'PR-APCA', 'submitted', [...$project, 'company' => 'APCA']);
        $this->request($requester, 'PR-FINANCE', 'submitted', [...$project, 'department_id' => $requester->employee->department_id]);

        $this->actingAs($processor)->get('/panel')->assertOk()
            ->assertSee('<h2 id="dashboard-pr-sr-heading">PR/SR</h2>', false)
            ->assertSeeText('Pengumpulan penawaran')
            ->assertSeeText('PR-PROJECT-6')
            ->assertDontSeeText('PR-PROJECT-1')
            ->assertDontSeeText('PR-PENDING')
            ->assertDontSeeText('PR-APCA')
            ->assertDontSeeText('PR-HO')
            ->assertDontSeeText('PR-FINANCE')
            ->assertDontSeeText('Purchasing Officer');

        Livewire::test(Dashboard::class)
            ->call('nextPage', 'pr-sr-page')
            ->assertSee('PR-PROJECT-1')
            ->assertDontSee('PR-PROJECT-6')
            ->set('scmCompany', 'APCA')
            ->assertSee('Tidak ada pengajuan PR/SR yang cocok dengan filter ini.')
            ->assertDontSee('PR-APCA');
    }

    public function test_tracking_combines_need_and_company_filters_and_shows_the_project_master(): void
    {
        $requester = $this->actor('Engineering', 'scm.request');
        $master = ProjectNumbering::create(['code' => 'FAC/24/010', 'name' => 'Pemeliharaan Fasilitas Utara', 'is_active' => false]);
        $this->request($requester, 'PR-KPMOG-HO', 'submitted', ['company' => 'KPMOG']);
        $this->request($requester, 'PR-APCA-HO', 'submitted', ['company' => 'APCA']);
        $this->request($requester, 'PR-KPMOG-PROJECT', 'submitted', [
            'company' => 'KPMOG', 'need_type' => 'PROJECT', 'project_numbering_id' => $master->id,
        ]);
        for ($number = 1; $number <= 5; $number++) {
            $this->request($requester, 'PR-APCA-PROJECT-'.$number, 'submitted', ['company' => 'APCA', 'need_type' => 'PROJECT']);
        }

        $this->actingAs($requester)->get('/panel?pr-sr-jenis=PROJECT&pr-sr-perusahaan=KPMOG')->assertOk()
            ->assertSeeInOrder(['Project &middot; KPMOG', 'PR-KPMOG-PROJECT', $master->code, $master->name], false)
            ->assertDontSeeText('PR-KPMOG-HO')
            ->assertDontSeeText('PR-APCA-PROJECT-1');

        Livewire::test(Dashboard::class)
            ->set('scmNeedType', 'PROJECT')
            ->call('nextPage', 'pr-sr-page')
            ->assertSee('PR-KPMOG-PROJECT')
            ->set('scmCompany', 'APCA')
            ->assertSee('PR-APCA-PROJECT-1')
            ->assertDontSee('PR-KPMOG-PROJECT')
            ->assertDontSee('PR-APCA-HO')
            ->set('scmNeedType', 'HO')
            ->assertSee('PR-APCA-HO')
            ->assertDontSee('PR-KPMOG-HO')
            ->assertDontSee('PR-APCA-PROJECT-1');
    }

    public function test_purchasing_officer_sees_ho_and_dashboard_visibility_follows_preferences(): void
    {
        $officer = $this->actor('General Affairs', 'ga.process');
        $request = $this->request($officer, 'SR-HO', 'submitted', ['kind' => 'SR']);
        $this->request($officer, 'SR-PROJECT', 'submitted', ['kind' => 'SR', 'need_type' => 'PROJECT']);

        $this->actingAs($officer)->get('/panel')->assertOk()
            ->assertSeeInOrder(['SR-HO', 'Posisi saat ini', 'Purchasing Officer', 'Tahap proses', 'Menunggu penerimaan'])
            ->assertDontSeeText('SR-PROJECT');
        $this->get('/panel/purchasing-officer/pengadaan?permintaan='.$request->id)->assertOk()->assertSeeText('SR-HO');

        $officer->update(['dashboard_sections' => ['reminders']]);
        $this->get('/panel')->assertOk()->assertDontSeeText('SR-HO');

        $this->actingAs($this->actor('IT'))->get('/panel')->assertOk()->assertDontSee('id="dashboard-pr-sr-heading"', false);
    }

    public function test_position_follows_cbe_signatures_then_returns_to_scm_for_legal_documents(): void
    {
        $processor = $this->actor('Engineering', 'scm.process');
        $approver = $this->actor('Finance');
        $request = $this->request($processor, 'PR-CBE', 'selected', ['need_type' => 'PROJECT']);
        $cbe = $request->cbe()->create(['number' => 'CBE-01', 'date' => today(), 'status' => 'final', 'prepared_by' => $processor->id]);
        $cbe->approvals()->createMany([
            ['position' => 0, 'role' => 'prepared', 'user_id' => $processor->id, 'assigned_name' => $processor->name, 'status' => 'signed'],
            ['position' => 1, 'role' => 'acknowledged', 'user_id' => $approver->id, 'assigned_name' => $approver->name],
        ]);

        $this->actingAs($processor)->get('/panel')->assertOk()
            ->assertSeeInOrder(['PR-CBE', 'Finance', 'Menunggu persetujuan CBE (Acknowledged By)']);

        $cbe->approvals()->where('status', 'pending')->update(['status' => 'signed']);
        $request->update(['status' => 'legal']);
        $this->get('/panel')->assertOk()->assertSeeInOrder(['PR-CBE', 'SCM', 'Pengumpulan legalitas']);
    }

    private function actor(string $department, ?string $permission = null): User
    {
        $department = Department::create(['code' => str($department)->slug(), 'name' => $department, 'is_active' => true]);
        $user = User::factory()->create();
        Employee::create(['user_id' => $user->id, 'department_id' => $department->id, 'name' => $user->name, 'is_active' => true]);

        if ($permission !== null) {
            $user->directPermissions()->attach(Permission::firstOrCreate(['code' => $permission], ['name' => $permission, 'is_active' => true]));
        }

        return $user;
    }

    private function request(User $user, string $number, string $status, array $attributes = []): Procurement
    {
        return Procurement::create([
            'number' => $number,
            'kind' => 'PR',
            'date' => today(),
            'requester_id' => $user->id,
            'department_id' => $user->employee->department_id,
            'title' => 'Kebutuhan operasional',
            'reason' => '',
            'status' => $status,
            ...$attributes,
        ]);
    }
}
