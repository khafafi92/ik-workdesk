<?php

namespace Tests\Feature;

use App\Exports\FinanceBudgetUsageExport;
use App\Filament\Pages\DepartmentBudgets;
use App\Filament\Pages\FinanceBudgets;
use App\Models\Department;
use App\Models\Employee;
use App\Models\FinanceAccount;
use App\Models\Permission;
use App\Models\TravelDocument;
use App\Models\User;
use App\Services\Finance\BudgetMasters;
use App\Services\Finance\BudgetLedger;
use Database\Seeders\ScmSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
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
        Livewire::actingAs($user)->test(FinanceBudgets::class)->assertSee('No major budget yet.')
            ->set('budget', ['company' => 'KPMOG', 'year' => '2026', 'amount' => '1000'])
            ->call('createBudget')->assertHasNoErrors()->assertSee('Allocation KPMOG 2026');
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

    public function test_budget_report_replaces_spd_commitment_with_approved_lpj_actual(): void
    {
            $this->seed(ScmSeeder::class);
            $user = User::factory()->create(['is_admin' => false]);
            $department = Department::create(['code' => 'SPD-BUDGET-QA', 'name' => 'Divisi SPD QA', 'is_active' => true]);
            $employee = Employee::create(['user_id' => $user->id, 'department_id' => $department->id, 'name' => $user->name, 'is_active' => true]);
            $account = FinanceAccount::create(['company' => 'KPMOG', 'code' => 'SPD-QA', 'name' => 'Biaya Perjalanan', 'is_active' => true,
                'created_by' => $user->id, 'updated_by' => $user->id]);
            $user = $this->budgetManager($user);
            $masters = app(BudgetMasters::class);
            $budget = $masters->createBudget($user, ['company' => 'KPMOG', 'year' => 2026, 'amount' => '1000']);
            $scope = $masters->createScope($user, $budget->id, ['need_type' => 'HO', 'amount' => '1000']);
            $masters->createLine($user, $scope->id, ['department_id' => $department->id, 'account_id' => $account->id, 'amount' => '1000']);
            $line = $scope->lines()->sole();
            $spd = TravelDocument::create(['kind' => 'spd', 'company' => 'KPMOG', 'number' => 'SPD-BUDGET-QA', 'number_key' => 'spd-budget-qa',
                'issued_on' => '2026-11-18', 'applied_on' => '2026-11-18', 'employee_id' => $employee->id, 'department_id' => $department->id,
                'created_by' => $user->id, 'finance_budget_line_id' => $line->id, 'status' => 'approved', 'data' => ['purpose' => 'Perjalanan QA'],
                'employee_snapshot' => ['name' => $user->name, 'department' => $department->name], 'total_minor' => 30000]);
            TravelDocument::create(['kind' => 'lpj', 'company' => 'KPMOG', 'number' => 'LPJ-BUDGET-QA', 'number_key' => 'lpj-budget-qa',
                'issued_on' => '2026-11-18', 'applied_on' => '2026-11-18', 'employee_id' => $employee->id, 'department_id' => $department->id,
                'created_by' => $user->id, 'spd_id' => $spd->id, 'finance_budget_line_id' => $line->id, 'status' => 'approved',
                'data' => ['purpose' => 'Perjalanan QA'], 'employee_snapshot' => ['name' => $user->name, 'department' => $department->name],
                'total_minor' => 25000]);

            $summary = app(BudgetLedger::class)->lineSummary($line);
            $report = app(BudgetLedger::class)->budgetReport($budget)->sole();

            $this->assertSame(25000, $summary['used_minor']);
            $this->assertSame(0, $summary['committed_minor']);
            $this->assertSame(75000, $summary['available_minor']);
            $this->assertSame('LPJ Realization', $report['entries']->sole()['status']);
            $this->assertSame('LPJ-BUDGET-QA', $report['entries']->sole()['related_number']);
            $this->assertSame(25000, $report['entries']->sole()['realized_minor']);
    }

    public function test_budget_usage_export_has_accounting_summary_and_hierarchical_details(): void
    {
        $this->seed(ScmSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $requester = User::factory()->create(['is_admin' => false]);
        $department = Department::create(['code' => 'SPD-EXPORT-QA', 'name' => 'Divisi Export QA', 'is_active' => true]);
        $employee = Employee::create(['user_id' => $requester->id, 'department_id' => $department->id, 'name' => $requester->name, 'is_active' => true]);
        $account = FinanceAccount::create(['company' => 'KPMOG', 'code' => 'SPD-EXPORT-QA', 'name' => 'Biaya Perjalanan', 'is_active' => true,
            'created_by' => $requester->id, 'updated_by' => $requester->id]);
        $manager = $this->budgetManager($requester);
        $masters = app(BudgetMasters::class);
        $budget = $masters->createBudget($manager, ['company' => 'KPMOG', 'year' => 2026, 'amount' => '1500']);
        $scope = $masters->createScope($manager, $budget->id, ['need_type' => 'HO', 'amount' => '1000']);
        $masters->createLine($manager, $scope->id, ['department_id' => $department->id, 'account_id' => $account->id, 'amount' => '800']);
        $line = $scope->lines()->sole();
        $spd = TravelDocument::create(['kind' => 'spd', 'company' => 'KPMOG', 'number' => 'SPD-EXPORT-QA', 'number_key' => 'spd-export-qa',
            'issued_on' => '2026-11-18', 'applied_on' => '2026-11-18', 'employee_id' => $employee->id, 'department_id' => $department->id,
            'created_by' => $manager->id, 'finance_budget_line_id' => $line->id, 'status' => 'approved', 'data' => ['purpose' => '=1+1'],
            'employee_snapshot' => ['name' => $manager->name, 'department' => $department->name], 'total_minor' => 30000]);
        TravelDocument::create(['kind' => 'lpj', 'company' => 'KPMOG', 'number' => 'LPJ-EXPORT-QA', 'number_key' => 'lpj-export-qa',
            'issued_on' => '2026-11-18', 'applied_on' => '2026-11-18', 'employee_id' => $employee->id, 'department_id' => $department->id,
            'created_by' => $manager->id, 'spd_id' => $spd->id, 'finance_budget_line_id' => $line->id, 'status' => 'approved',
            'data' => ['purpose' => '=1+1'], 'employee_snapshot' => ['name' => $manager->name, 'department' => $department->name],
            'total_minor' => 25000]);

        $budget->load(['scopes.project', 'scopes.lines.department', 'scopes.lines.account']);
        $report = app(BudgetLedger::class)->budgetReport($budget);
        $export = new FinanceBudgetUsageExport($budget, $report);
        $rows = $export->array();
        $this->assertSame(1500.0, $rows[3][7]);
        $this->assertSame(1000.0, $rows[4][7]);
        $this->assertSame(500.0, $rows[5][7]);
        $this->assertSame(250.0, $rows[6][9]);
        $this->assertSame(1250.0, $rows[8][11]);
        $this->assertSame('HEAD OFFICE ALLOCATION', $rows[11][0]);
        $this->assertSame('DIVISION / COA ALLOCATION', $rows[12][0]);
        $this->assertSame('LPJ · LPJ-EXPORT-QA', $rows[13][2]);
        $this->assertSame(250.0, $rows[13][8]);
        $this->assertIsFloat($rows[13][3]);

        $bytes = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);
        $file = tempnam(sys_get_temp_dir(), 'budget-export-');
        try {
            file_put_contents($file, $bytes);
            Cell::setValueBinder(new DefaultValueBinder);
            $workbook = IOFactory::load($file);
            $sheet = $workbook->getActiveSheet();
            $this->assertSame('=1+1', $sheet->getCell('A14')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A14')->getDataType());
            $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('D14')->getDataType());
            $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('I14')->getDataType());
            $this->assertSame(NumberFormat::FORMAT_DATE_DDMMYYYY, $sheet->getStyle('D14')->getNumberFormat()->getFormatCode());
            $this->assertSame('"IDR" #,##0.00;[Red]("IDR" #,##0.00);"-"', $sheet->getStyle('H4')->getNumberFormat()->getFormatCode());
            $workbook->disconnectWorksheets();
        } finally {
            unlink($file);
        }

        Excel::fake();
        Livewire::actingAs($manager)->test(FinanceBudgets::class)
            ->call('openBudget', $budget->id)
            ->assertSee('Export to Excel')
            ->call('exportBudget')
            ->assertHasNoErrors();
        Excel::assertDownloaded('penggunaan-anggaran-kpmog-2026-idr.xlsx', fn (FinanceBudgetUsageExport $download): bool => $download->array()[8][11] === 1250.0);
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

    private function budgetManager(User $user): User
    {
        $user->directPermissions()->attach(Permission::where('code', 'finance.budget.manage')->sole());

        return $user->fresh();
    }
}
