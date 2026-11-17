<?php

namespace Tests\Feature;

use App\Filament\Pages\DepartmentBudgets;
use App\Filament\Pages\FinanceBudgets;
use App\Models\Department;
use App\Models\Employee;
use App\Models\FinanceAccount;
use App\Models\Permission;
use App\Models\User;
use App\Services\Finance\BudgetMasters;
use Database\Seeders\ScmSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FinanceBudgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_budget_hierarchy_validates_totals_company_and_access(): void
    {
        $this->seed(ScmSeeder::class);
        $user = User::factory()->create(['is_admin' => false]);
        $user->directPermissions()->attach(Permission::where('code', 'finance.budget.manage')->sole());
        $department = Department::create(['code' => 'BUDGET-QA', 'name' => 'Budget QA', 'is_active' => true]);
        $account = FinanceAccount::create(['company' => 'KPMOG', 'code' => '001', 'name' => 'QA', 'is_active' => true, 'created_by' => $user->id, 'updated_by' => $user->id]);
        $other = FinanceAccount::create(['company' => 'APCA', 'code' => '001', 'name' => 'QA', 'is_active' => true, 'created_by' => $user->id, 'updated_by' => $user->id]);
        $service = app(BudgetMasters::class);
        $budget = $service->createBudget($user, ['company' => 'KPMOG', 'year' => 2026, 'amount' => '1000']);
        $scope = $service->createScope($user, $budget->id, ['need_type' => 'HO', 'amount' => '800']);
        $service->createLine($user, $scope->id, ['department_id' => $department->id, 'account_id' => $account->id, 'amount' => '600']);
        $this->assertSame(60000, $scope->lines()->sole()->amount_minor);
        try {
            $service->updateAmount($user, 'budget', $budget->id, $budget->version, '700');
            $this->fail('Anggaran tidak boleh di bawah alokasi.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('amount', $error->errors());
        }
        $service->updateAmount($user, 'scope', $scope->id, $scope->version, '900');
        try {
            $service->updateAmount($user, 'scope', $scope->id, $scope->version, '950');
            $this->fail('Perubahan lama harus ditolak.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('amount', $error->errors());
        }
        try {
            $service->createLine($user, $scope->id, ['department_id' => $department->id, 'account_id' => $other->id, 'amount' => '100']);
            $this->fail('COA perusahaan lain harus ditolak.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('account_id', $error->errors());
        }
        $second = FinanceAccount::create(['company' => 'KPMOG', 'code' => '002', 'name' => 'QA 2', 'is_active' => true, 'created_by' => $user->id, 'updated_by' => $user->id]);
        try {
            $service->createLine($user, $scope->id, ['department_id' => $department->id, 'account_id' => $second->id, 'amount' => '301']);
            $this->fail('Alokasi melebihi anggaran harus ditolak.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('amount', $error->errors());
        }
        $outsider = User::factory()->create(['is_admin' => false]);
        $this->expectException(HttpException::class);
        $service->createBudget($outsider, ['company' => 'APCA', 'year' => 2026, 'amount' => '100']);
    }

    public function test_budget_page_renders_and_saves_an_annual_budget(): void
    {
        $this->seed(ScmSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create(['is_admin' => false]);
        $user->directPermissions()->attach(Permission::where('code', 'finance.budget.manage')->sole());
        Livewire::actingAs($user)->test(FinanceBudgets::class)->assertSee('Belum ada anggaran utama.')
            ->set('budget', ['company' => 'KPMOG', 'year' => '2026', 'amount' => '1000'])
            ->call('createBudget')->assertHasNoErrors()->assertSee('Alokasi KPMOG 2026');
    }

    public function test_budgets_keep_currencies_separate_for_the_same_company_and_year(): void
    {
        $this->seed(ScmSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create(['is_admin' => false]);
        $user->directPermissions()->attach(Permission::where('code', 'finance.budget.manage')->sole());
        $service = app(BudgetMasters::class);
        $idr = $service->createBudget($user, ['company' => 'KPMOG', 'year' => 2026, 'currency' => 'IDR', 'amount' => '1000']);
        $usd = $service->createBudget($user, ['company' => 'KPMOG', 'year' => 2026, 'currency' => 'USD', 'amount' => '100']);
        $this->assertNotSame($idr->id, $usd->id);
        $this->assertSame('USD', $usd->currency);
        $service->createScope($user, $usd->id, ['need_type' => 'HO', 'amount' => '80']);
        Livewire::actingAs($user)->test(FinanceBudgets::class)->call('openBudget', $usd->id)
            ->assertSee('USD 100,00')->assertSee('USD 80,00')->assertSee('USD 20,00');
        try {
            $service->createBudget($user, ['company' => 'KPMOG', 'year' => 2026, 'currency' => 'USD', 'amount' => '10']);
            $this->fail('Anggaran untuk perusahaan, tahun dan mata uang yang sama harus unik.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('year', $error->errors());
        }
    }

    public function test_department_budget_view_is_limited_to_accessible_departments(): void
    {
        $this->seed(ScmSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $manager = User::factory()->create(['is_admin' => false]);
        $manager->directPermissions()->attach(Permission::where('code', 'finance.budget.manage')->sole());
        $department = Department::create(['code' => 'QA-BUDGET-1', 'name' => 'Divisi QA satu', 'is_active' => true]);
        $other = Department::create(['code' => 'QA-BUDGET-2', 'name' => 'Divisi QA dua', 'is_active' => true]);
        $requester = User::factory()->create(['is_admin' => false]);
        Employee::create(['user_id' => $requester->id, 'department_id' => $department->id, 'name' => $requester->name, 'is_active' => true]);
        $requester->directPermissions()->attach(Permission::where('code', 'scm.request')->sole());
        $account = FinanceAccount::create(['company' => 'KPMOG', 'code' => 'VIEW-TEST', 'name' => 'Biaya QA', 'is_active' => true, 'created_by' => $manager->id, 'updated_by' => $manager->id]);
        $service = app(BudgetMasters::class);
        $budget = $service->createBudget($manager, ['company' => 'KPMOG', 'year' => 2026, 'currency' => 'USD', 'amount' => '1000']);
        $scope = $service->createScope($manager, $budget->id, ['need_type' => 'HO', 'amount' => '1000']);
        $service->createLine($manager, $scope->id, ['department_id' => $department->id, 'account_id' => $account->id, 'amount' => '100']);
        $service->createLine($manager, $scope->id, ['department_id' => $other->id, 'account_id' => $account->id, 'amount' => '200']);
        Livewire::actingAs($requester)->test(DepartmentBudgets::class)->assertSee('Divisi QA satu')->assertSee('USD 100,00')->assertDontSee('Divisi QA dua');
        Livewire::actingAs($requester)->test(FinanceBudgets::class)->assertForbidden();
    }
}
