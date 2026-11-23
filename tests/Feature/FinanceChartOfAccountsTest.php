<?php

namespace Tests\Feature;

use App\Filament\Pages\FinanceChartOfAccounts;
use App\Models\Department;
use App\Models\FinanceAccount;
use App\Models\Permission;
use App\Models\User;
use App\Services\Finance\BudgetMasters;
use Database\Seeders\ScmSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FinanceChartOfAccountsTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->seed(ScmSeeder::class);
        $this->manager = User::factory()->create(['is_admin' => false]);
        $this->manager->directPermissions()->attach(Permission::where('code', 'finance.coa.manage')->sole());
    }

    public function test_managed_accounts_are_company_scoped_audited_and_can_be_deactivated(): void
    {
        $page = Livewire::actingAs($this->manager)->test(FinanceChartOfAccounts::class)
            ->assertSee('No suitable COAs yet, add accounts to the Finance list.')
            ->set('account', ['company' => 'KPMOG', 'code' => ' 001-a ', 'name' => 'Akun QA', 'is_active' => true])
            ->call('saveAccount')->assertHasNoErrors()->assertSee('001-A');
        $account = FinanceAccount::sole();
        $this->assertSame('001-A', $account->code);
        $this->assertDatabaseHas('scm_audits', ['entity_type' => $account->getMorphClass(), 'entity_id' => $account->id, 'action' => 'simpan_coa']);
        $page->call('editAccount', $account->id)->set('account.is_active', false)->call('saveAccount')->assertHasNoErrors();
        $this->assertFalse($account->fresh()->is_active);

        $page->set('account', ['company' => 'KPMOG', 'code' => '001-a', 'name' => 'Duplikat', 'is_active' => true])
            ->call('saveAccount')->assertHasErrors(['code']);
        $page->set('account.company', 'APCA')->call('saveAccount')->assertHasNoErrors();
        $this->assertSame(2, FinanceAccount::count());
    }

    public function test_stale_edits_are_rejected(): void
    {
        $account = FinanceAccount::create(['company' => 'KPMOG', 'code' => '001', 'name' => 'Akun QA', 'is_active' => true,
            'version' => 1, 'created_by' => $this->manager->id, 'updated_by' => $this->manager->id]);
        $page = Livewire::actingAs($this->manager)->test(FinanceChartOfAccounts::class)->call('editAccount', $account->id);
        $account->update(['name' => 'Diubah pengguna lain', 'version' => 2]);
        $page->set('account.name', 'Edit lama')->call('saveAccount')->assertHasErrors(['account']);
        $this->assertSame('Diubah pengguna lain', $account->fresh()->name);
    }

    public function test_payment_permission_does_not_grant_master_coa_access(): void
    {
        $cashier = User::factory()->create(['is_admin' => false]);
        $cashier->directPermissions()->attach(Permission::where('code', 'finance.payments')->sole());
        Livewire::actingAs($cashier)->test(FinanceChartOfAccounts::class)->assertForbidden();
    }

    public function test_account_hierarchy_has_three_levels_and_prevents_cycles_and_cross_company_parents(): void
    {
        $page = Livewire::actingAs($this->manager)->test(FinanceChartOfAccounts::class);
        $page->set('account', ['company' => 'KPMOG', 'code' => '1', 'name' => 'Induk', 'is_active' => true])->call('saveAccount')->assertHasNoErrors();
        $root = FinanceAccount::where('code', '1')->sole();
        $page->set('account', ['company' => 'KPMOG', 'code' => '1.1', 'name' => 'Turunan 1', 'parent_id' => $root->id, 'is_active' => true])->call('saveAccount')->assertHasNoErrors();
        $child = FinanceAccount::where('code', '1.1')->sole();
        $page->set('account', ['company' => 'KPMOG', 'code' => '1.1.1', 'name' => 'Turunan 2', 'parent_id' => $child->id, 'is_active' => true])->call('saveAccount')->assertHasNoErrors();
        $grandchild = FinanceAccount::where('code', '1.1.1')->sole();
        $page->set('account', ['company' => 'KPMOG', 'code' => '1.1.1.1', 'name' => 'Terlalu dalam', 'parent_id' => $grandchild->id, 'is_active' => true])->call('saveAccount')->assertHasErrors(['parent_id']);
        $page->call('editAccount', $root->id)->set('account.parent_id', $grandchild->id)->call('saveAccount')->assertHasErrors(['parent_id']);
        $page->call('newAccount')->set('account', ['company' => 'APCA', 'code' => '2', 'name' => 'Lain', 'parent_id' => $root->id, 'is_active' => true])->call('saveAccount')->assertHasErrors(['parent_id']);
        $this->assertSame(3, FinanceAccount::count());
        $this->assertSame($child->id, $grandchild->fresh()->parent_id);
    }

    public function test_all_three_account_levels_can_be_used_for_budget_allocations(): void
    {
        $this->manager->directPermissions()->attach(Permission::where('code', 'finance.budget.manage')->sole());
        $base = ['company' => 'KPMOG', 'name' => 'QA', 'is_active' => true, 'created_by' => $this->manager->id, 'updated_by' => $this->manager->id];
        $root = FinanceAccount::create([...$base, 'code' => 'R']);
        $child = FinanceAccount::create([...$base, 'code' => 'C', 'parent_id' => $root->id]);
        $leaf = FinanceAccount::create([...$base, 'code' => 'L', 'parent_id' => $child->id]);
        $department = Department::create(['code' => 'COA-TEST', 'name' => 'COA test', 'is_active' => true]);
        $budgets = app(BudgetMasters::class);
        $budget = $budgets->createBudget($this->manager->fresh(), ['company' => 'KPMOG', 'year' => 2026, 'amount' => '300']);
        $scope = $budgets->createScope($this->manager->fresh(), $budget->id, ['need_type' => 'HO', 'amount' => '300']);
        foreach ([$root, $child, $leaf] as $account) {
            $budgets->createLine($this->manager->fresh(), $scope->id, ['department_id' => $department->id, 'account_id' => $account->id, 'amount' => '100']);
        }
        $this->assertSame(3, $scope->lines()->count());
    }
}
