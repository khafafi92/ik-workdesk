<?php

namespace Tests\Feature;

use App\Filament\Pages\ScmProcurement;
use App\Models\FinanceBudgetApprovalPolicy;
use App\Models\Permission;
use App\Models\User;
use App\Services\Finance\BudgetApprovalPolicies;
use Database\Seeders\ScmSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class FinanceBudgetApprovalPoliciesTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_policy_is_scoped_by_company_and_need_type_and_rejects_stale_updates(): void
    {
        $this->seed(ScmSeeder::class);
        $admin = User::factory()->create(['is_admin' => false]);
        $admin->directPermissions()->attach(Permission::where('code', 'scm.configure')->sole());
        $users = User::factory()->count(4)->create();
        $service = app(BudgetApprovalPolicies::class);
        $data = ['company' => 'KPMOG', 'need_type' => 'HO', 'finance_manager_id' => $users[0]->id,
            'cbo_id' => $users[1]->id, 'coo_id' => $users[2]->id, 'version' => 0];
        $ho = $service->save($admin, $data);
        $project = $service->save($admin, [...$data, 'need_type' => 'PROJECT', 'coo_id' => $users[3]->id]);
        $this->assertNotSame($ho->coo_id, $project->coo_id);
        $this->assertSame(2, FinanceBudgetApprovalPolicy::count());
        $this->assertDatabaseHas('scm_audits', ['entity_type' => $ho->getMorphClass(), 'entity_id' => $ho->id, 'action' => 'master_approval_anggaran']);
        $service->save($admin, [...$data, 'version' => 1]);
        try {
            $service->save($admin, [...$data, 'version' => 1]);
            $this->fail('Perubahan lama harus ditolak.');
        } catch (ValidationException $error) {
            $this->assertNotEmpty($error->errors());
        }
        try {
            $service->save($admin, [...$data, 'need_type' => 'APCA', 'coo_id' => $users[0]->id]);
            $this->fail('Jenis atau penandatangan tidak valid harus ditolak.');
        } catch (ValidationException $error) {
            $this->assertNotEmpty($error->errors());
        }
    }

    public function test_policy_can_be_managed_from_existing_master_approval(): void
    {
        $this->seed(ScmSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::factory()->create(['is_admin' => false]);
        $admin->directPermissions()->attach(Permission::where('code', 'scm.configure')->sole());
        $users = User::factory()->count(3)->create();
        Livewire::actingAs($admin)->test(ScmProcurement::class)->call('switchSection', 'settings')
            ->set('approvalDocument', 'BUDGET')->assertSee('Aturan ini belum aktif')
            ->set('budgetApprovalPolicy.finance_manager_id', $users[0]->id)
            ->set('budgetApprovalPolicy.cbo_id', $users[1]->id)
            ->set('budgetApprovalPolicy.coo_id', $users[2]->id)
            ->call('saveBudgetApprovalPolicy')->assertHasNoErrors()->assertSee($users[0]->name);
    }
}
