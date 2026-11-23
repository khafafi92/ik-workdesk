<?php

namespace App\Services\Finance;

use App\Models\FinanceBudget;
use App\Models\FinanceBudgetLine;
use App\Models\Scm\PurchaseOrder;
use App\Models\TravelDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BudgetLedger
{
    public function linesForContext(string $company, string $date, int $departmentId, string $needType, ?int $projectNumberingId): Collection
    {
        if (! Schema::hasTable('finance_budget_lines') || ! Schema::hasTable('finance_budgets')) {
            return collect();
        }

        return FinanceBudgetLine::query()->with(['scope.budget', 'scope.project', 'department', 'account'])
            ->where('department_id', $departmentId)
            ->whereHas('scope', function ($scope) use ($company, $date, $needType, $projectNumberingId): void {
                $scope->where('need_type', $needType)
                    ->whereHas('budget', fn ($budget) => $budget->where('company', $company)
                        ->where('year', (int) substr($date, 0, 4)))
                    ->when($needType === 'PROJECT', fn ($project) => $project->where('project_numbering_id', $projectNumberingId))
                    ->when($needType === 'HO', fn ($ho) => $ho->whereNull('project_numbering_id'));
            })->orderBy('account_id')->get();
    }

    public function resolveLine(?int $lineId, string $company, string $date, int $departmentId, string $needType, ?int $projectNumberingId): ?FinanceBudgetLine
    {
        $lines = $this->linesForContext($company, $date, $departmentId, $needType, $projectNumberingId);

        if (! $lineId) {
            if ($lines->isNotEmpty()) {
                throw ValidationException::withMessages(['finance_budget_line_id' => 'Select a budget for this company, year, project, and division.']);
            }

            return null;
        }

        $line = $lines->firstWhere('id', $lineId);
        if (! $line) {
            throw ValidationException::withMessages(['finance_budget_line_id' => 'Budget efforts don\'t match companies, years, projects, or divisions.']);
        }

        return $line;
    }

    public function lineSummary(FinanceBudgetLine $line, ?int $exceptOrderId = null): array
    {
        $orders = Schema::hasTable('scm_purchase_orders') && Schema::hasColumn('scm_purchase_orders', 'status')
            && Schema::hasColumn('scm_requests', 'finance_budget_line_id')
            ? PurchaseOrder::query()
            ->with(['request.department', 'request.projectNumbering', 'payments'])
            ->whereHas('request', fn ($request) => $request->where('finance_budget_line_id', $line->id))
            ->when($exceptOrderId, fn ($query) => $query->whereKeyNot($exceptOrderId))
            ->get()
            : collect();

        $poPaid = 0;
        $poCommitted = 0;
        foreach ($orders as $order) {
            if ($order->status === 'cancelled') {
                continue;
            }
            $total = (int) data_get($order->snapshot, 'evaluation.total_minor', 0);
            $paid = (int) $order->payments->where('status', 'paid')->sum('amount_minor');
            $poPaid += $paid;
            $poCommitted += max(0, $total - $paid);
        }

        $spds = Schema::hasTable('travel_documents') && Schema::hasColumn('travel_documents', 'finance_budget_line_id')
            ? TravelDocument::query()->with(['lpj'])
            ->where('kind', 'spd')->where('finance_budget_line_id', $line->id)
            ->whereIn('status', ['submitted', 'approved'])->get()
            : collect();
        $spdActual = 0;
        $spdCommitted = 0;
        foreach ($spds as $spd) {
            if ($spd->lpj?->status === 'approved') {
                $spdActual += $spd->lpj->total_minor;
            } else {
                $spdCommitted += $spd->total_minor;
            }
        }

        $committed = $poCommitted + $spdCommitted;
        $realized = $poPaid + $spdActual;
        $used = $realized + $committed;

        return ['realized_minor' => $realized, 'committed_minor' => $committed,
            'used_minor' => $used, 'available_minor' => $line->amount_minor - $used,
            'over_minor' => max(0, $used - $line->amount_minor)];
    }

    public function budgetReport(FinanceBudget $budget): Collection
    {
        $lines = FinanceBudgetLine::query()->with(['department', 'account', 'scope.project'])
            ->whereHas('scope', fn ($scope) => $scope->where('budget_id', $budget->id))
            ->orderBy('scope_id')->orderBy('department_id')->orderBy('account_id')->get();

        return $lines->map(function (FinanceBudgetLine $line): array {
            $summary = $this->lineSummary($line);
            $entries = $this->lineEntries($line);

            return ['line' => $line, ...$summary, 'entries' => $entries];
        });
    }

    private function lineEntries(FinanceBudgetLine $line): Collection
    {
        $orders = Schema::hasTable('scm_purchase_orders') && Schema::hasColumn('scm_purchase_orders', 'status')
            && Schema::hasColumn('scm_requests', 'finance_budget_line_id')
            ? PurchaseOrder::query()->with(['request.department', 'request.projectNumbering', 'payments'])
            ->whereHas('request', fn ($request) => $request->where('finance_budget_line_id', $line->id))
            ->orderBy('issued_at')->get()
            ->map(function (PurchaseOrder $order): array {
                $total = (int) data_get($order->snapshot, 'evaluation.total_minor', 0);
                $paid = $order->status === 'cancelled' ? 0 : (int) $order->payments->where('status', 'paid')->sum('amount_minor');
                $cancelled = $order->status === 'cancelled';

                return ['date' => $order->issued_at, 'kind' => $order->request->kind,
                    'number' => $order->request->number, 'related_number' => $order->number,
                    'department' => $order->request->department->name, 'project' => $order->request->projectNumbering?->code,
                    'purpose' => $order->request->title ?: $order->request->reason,
                    'status' => $cancelled ? 'PO canceled, commitment off' : 'PO aktif',
                    'amount_minor' => $total, 'used_minor' => $cancelled ? 0 : $total,
                    'realized_minor' => $paid, 'committed_minor' => $cancelled ? 0 : max(0, $total - $paid)];
            })
            : collect();

        $spds = Schema::hasTable('travel_documents') && Schema::hasColumn('travel_documents', 'finance_budget_line_id')
            ? TravelDocument::query()->with(['lpj', 'department', 'projectNumbering'])
            ->where('kind', 'spd')->where('finance_budget_line_id', $line->id)
            ->whereIn('status', ['submitted', 'approved'])->orderBy('applied_on')->get()
            ->map(function (TravelDocument $spd): array {
                $lpj = $spd->lpj?->status === 'approved' ? $spd->lpj : null;
                $amount = $lpj?->total_minor ?? $spd->total_minor;

                return ['date' => $lpj?->applied_on ?? $spd->applied_on, 'kind' => 'SPD',
                    'number' => $spd->number, 'related_number' => $lpj?->number,
                    'department' => $spd->department?->name ?? $spd->employee_snapshot['department'] ?? '',
                    'project' => $spd->projectNumbering?->code,
                    'purpose' => $spd->data['purpose'] ?? '',
                    'status' => $lpj ? 'LPJ Realization' : 'SPD commitment',
                    'amount_minor' => $amount, 'used_minor' => $amount, 'realized_minor' => $lpj ? $amount : 0,
                    'committed_minor' => $lpj ? 0 : $amount];
            })
            : collect();

        return $orders->concat($spds)->sortBy('date')->values();
    }
}
