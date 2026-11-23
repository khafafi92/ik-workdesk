<?php

namespace App\Filament\Pages;

use App\Models\FinanceBudgetLine;
use App\Services\Finance\BudgetLedger;
use App\Services\Scm\Access;
use Filament\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Livewire\WithPagination;
use UnitEnum;

class DepartmentBudgets extends Page
{
    use WithPagination;

    protected static ?string $slug = 'permintaan/anggaran-departemen';

    protected static ?string $title = 'Department budget';

    protected static ?string $navigationLabel = 'Department budget';

    protected static string|UnitEnum|null $navigationGroup = 'Permintaan';

    protected string $view = 'filament.pages.department-budgets';

    public static function canAccess(): bool
    {
        return Access::has(auth()->user(), 'scm.request');
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $missing = ! Schema::hasTable('finance_budget_lines') || ! Schema::hasTable('finance_budget_scopes')
            || ! Schema::hasTable('finance_budgets') || ! Schema::hasColumn('finance_budgets', 'currency');
        $user = auth()->user();
        $lines = $missing ? new LengthAwarePaginator([], 0, 15) : FinanceBudgetLine::query()
            ->with(['scope.budget', 'scope.project', 'department', 'account'])
            ->when(! $user->isSystemAdministrator(), fn ($query) => $query->whereIn('department_id', $user->accessibleDepartmentIds()))
            ->orderByDesc('id')->paginate(15);
        if (! $missing) {
            $lines->getCollection()->transform(function (FinanceBudgetLine $line): FinanceBudgetLine {
                $line->setAttribute('budget_usage', app(BudgetLedger::class)->lineSummary($line));

                return $line;
            });
        }

        return compact('missing', 'lines');
    }
}
