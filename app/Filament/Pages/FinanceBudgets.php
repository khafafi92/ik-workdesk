<?php

namespace App\Filament\Pages;

use App\Models\Department;
use App\Models\FinanceAccount;
use App\Models\FinanceBudget;
use App\Models\FinanceBudgetLine;
use App\Models\FinanceBudgetScope;
use App\Models\Scm\ProjectNumbering;
use App\Services\Finance\BudgetMasters;
use App\Services\Scm\Access;
use App\Services\Scm\Decimal;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Locked;
use UnitEnum;

class FinanceBudgets extends Page
{
    protected static ?string $slug = 'finance/master-anggaran';

    protected static ?string $title = 'Master anggaran';

    protected static ?string $navigationLabel = 'Master anggaran';

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected string $view = 'filament.pages.finance-budgets';

    #[Locked]
    public int $selectedBudget = 0;

    #[Locked]
    public string $editLevel = '';

    #[Locked]
    public int $editId = 0;

    #[Locked]
    public int $editVersion = 0;

    public string $editAmount = '';

    public array $budget = ['company' => '', 'year' => '', 'currency' => 'IDR', 'amount' => ''];

    public array $scope = ['need_type' => 'HO', 'project_numbering_id' => '', 'amount' => ''];

    public array $line = ['scope_id' => '', 'department_id' => '', 'account_id' => '', 'amount' => ''];

    public static function canAccess(): bool
    {
        return Access::has(auth()->user(), 'finance.budget.manage');
    }

    public function updatedScopeNeedType(string $value): void
    {
        if ($value === 'HO') {
            $this->scope['project_numbering_id'] = '';
        }
    }

    public function createBudget(): void
    {
        $record = app(BudgetMasters::class)->createBudget(auth()->user(), $this->budget);
        $this->reset('budget');
        $this->openBudget($record->id);
        Notification::make()->title('Anggaran utama tersimpan.')->success()->send();
    }

    public function openBudget(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        FinanceBudget::findOrFail($id);
        $this->selectedBudget = $id;
        $this->reset('scope', 'line');
        $this->cancelEdit();
        $this->resetValidation();
    }

    public function editAmount(string $level, int $id): void
    {
        abort_unless(static::canAccess(), 403);
        $record = match ($level) {
            'budget' => FinanceBudget::whereKey($this->selectedBudget)->findOrFail($id),
            'scope' => FinanceBudgetScope::where('budget_id', $this->selectedBudget)->findOrFail($id),
            'line' => FinanceBudgetLine::whereHas('scope', fn ($query) => $query->where('budget_id', $this->selectedBudget))->findOrFail($id),
            default => abort(404),
        };
        $this->editLevel = $level;
        $this->editId = $record->id;
        $this->editVersion = $record->version;
        $this->editAmount = Decimal::input($record->amount_minor);
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->editLevel = '';
        $this->editId = $this->editVersion = 0;
        $this->editAmount = '';
        $this->resetValidation();
    }

    public function saveAmount(): void
    {
        app(BudgetMasters::class)->updateAmount(auth()->user(), $this->editLevel, $this->editId, $this->editVersion, $this->editAmount);
        $this->cancelEdit();
        Notification::make()->title('Anggaran diperbarui.')->success()->send();
    }

    public function createScope(): void
    {
        app(BudgetMasters::class)->createScope(auth()->user(), $this->selectedBudget, $this->scope);
        $this->reset('scope');
        $this->resetValidation();
        Notification::make()->title('Alokasi HO atau project tersimpan.')->success()->send();
    }

    public function createLine(): void
    {
        abort_unless(static::canAccess(), 403);
        $scope = FinanceBudgetScope::where('budget_id', $this->selectedBudget)->findOrFail($this->line['scope_id']);
        app(BudgetMasters::class)->createLine(auth()->user(), $scope->id, $this->line);
        $this->reset('line');
        $this->resetValidation();
        Notification::make()->title('Alokasi departemen tersimpan.')->success()->send();
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $missing = ! Schema::hasTable('finance_budget_lines') || ! Schema::hasTable('finance_budget_scopes') || ! Schema::hasTable('finance_budgets') || ! Schema::hasTable('finance_accounts') || ! Schema::hasColumn('finance_budgets', 'currency');
        $budgets = $missing ? collect() : FinanceBudget::orderByDesc('year')->orderBy('company')->orderBy('currency')->get();
        $current = $missing || ! $this->selectedBudget ? null : FinanceBudget::with(['scopes.project', 'scopes.lines.department', 'scopes.lines.account'])->findOrFail($this->selectedBudget);
        $projects = $missing ? collect() : ProjectNumbering::where('is_active', true)->orderBy('code')->get();
        $departments = $missing ? collect() : Department::where('is_active', true)->orderBy('name')->get();
        $accounts = $current ? FinanceAccount::where('company', $current->company)->where('is_active', true)->orderBy('code')->get() : collect();

        return compact('missing', 'budgets', 'current', 'projects', 'departments', 'accounts');
    }
}
