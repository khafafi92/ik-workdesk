<?php

namespace App\Services\Finance;

use App\Models\FinanceAccount;
use App\Models\FinanceBudget;
use App\Models\FinanceBudgetLine;
use App\Models\FinanceBudgetScope;
use App\Models\Scm\ProjectNumbering;
use App\Models\User;
use App\Services\Scm\Access;
use App\Services\Scm\Decimal;
use App\Services\Scm\Workflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BudgetMasters
{
    private function allowed(User $actor): void
    {
        abort_unless(Access::has($actor, 'finance.budget.manage'), 403);
    }

    public function createBudget(User $actor, array $input): FinanceBudget
    {
        $this->allowed($actor);
        $input['currency'] ??= 'IDR';
        $data = Validator::make($input, ['company' => ['required', Rule::in(array_keys(config('scm.companies')))],
            'currency' => ['required', Rule::in(config('scm.currencies'))],
            'year' => ['required', 'integer', 'between:2000,2100', Rule::unique('finance_budgets')->where('company', $input['company'] ?? '')->where('currency', $input['currency'] ?? '')],
            'amount' => 'required'])->validate();
        $amount = Decimal::parse($data['amount']);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Anggaran utama harus lebih dari nol.']);
        }

        return DB::transaction(function () use ($actor, $data, $amount): FinanceBudget {
            $budget = FinanceBudget::create(['company' => $data['company'], 'year' => $data['year'], 'currency' => $data['currency'], 'amount_minor' => $amount,
                'version' => 1, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
            app(Workflow::class)->audit($actor, $budget, 'buat_anggaran', ['after' => $budget->getAttributes()]);

            return $budget;
        });
    }

    public function createScope(User $actor, int $budgetId, array $input): FinanceBudgetScope
    {
        $this->allowed($actor);
        $data = Validator::make($input, ['need_type' => ['required', Rule::in(['HO', 'PROJECT'])],
            'project_numbering_id' => 'nullable|integer|exists:scm_project_numbering,id', 'amount' => 'required'])->validate();
        $projectId = ($data['project_numbering_id'] ?? null) ?: null;
        if (($data['need_type'] === 'HO' && $projectId) || ($data['need_type'] === 'PROJECT' && (! $projectId || ! ProjectNumbering::whereKey($projectId)->where('is_active', true)->exists()))) {
            throw ValidationException::withMessages(['project_numbering_id' => 'Pilih project aktif untuk anggaran Project; kosongkan untuk HO.']);
        }
        $amount = Decimal::parse($data['amount']);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Alokasi harus lebih dari nol.']);
        }

        return DB::transaction(function () use ($actor, $budgetId, $data, $projectId, $amount): FinanceBudgetScope {
            $budget = FinanceBudget::lockForUpdate()->findOrFail($budgetId);
            $key = $projectId ? 'PROJECT:'.$projectId : 'HO';
            if ($budget->scopes()->where('scope_key', $key)->exists()) {
                throw ValidationException::withMessages(['need_type' => 'Alokasi HO atau project ini sudah ada.']);
            }
            if ($budget->scopes()->sum('amount_minor') + $amount > $budget->amount_minor) {
                throw ValidationException::withMessages(['amount' => 'Total alokasi HO dan project melebihi anggaran utama.']);
            }
            $scope = $budget->scopes()->create(['need_type' => $data['need_type'], 'project_numbering_id' => $projectId, 'scope_key' => $key, 'amount_minor' => $amount, 'version' => 1]);
            app(Workflow::class)->audit($actor, $scope, 'alokasi_anggaran_ho_project', ['budget_id' => $budget->id, 'after' => $scope->getAttributes()]);

            return $scope;
        }, 3);
    }

    public function createLine(User $actor, int $scopeId, array $input): void
    {
        $this->allowed($actor);
        $data = Validator::make($input, ['department_id' => 'required|integer|exists:departments,id', 'account_id' => 'required|integer|exists:finance_accounts,id', 'amount' => 'required'])->validate();
        $amount = Decimal::parse($data['amount']);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Alokasi harus lebih dari nol.']);
        }
        DB::transaction(function () use ($actor, $scopeId, $data, $amount): void {
            $scope = FinanceBudgetScope::lockForUpdate()->with('budget')->findOrFail($scopeId);
            if (! FinanceAccount::whereKey($data['account_id'])->where('company', $scope->budget->company)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['account_id' => 'Pilih COA aktif untuk perusahaan anggaran ini.']);
            }
            if ($scope->lines()->where('department_id', $data['department_id'])->where('account_id', $data['account_id'])->exists()) {
                throw ValidationException::withMessages(['account_id' => 'Alokasi departemen dan COA ini sudah ada.']);
            }
            if ($scope->lines()->sum('amount_minor') + $amount > $scope->amount_minor) {
                throw ValidationException::withMessages(['amount' => 'Total alokasi departemen melebihi anggaran HO atau project.']);
            }
            $line = $scope->lines()->create(['department_id' => $data['department_id'], 'account_id' => $data['account_id'], 'amount_minor' => $amount, 'version' => 1]);
            app(Workflow::class)->audit($actor, $line, 'alokasi_anggaran_departemen', ['scope_id' => $scope->id, 'after' => $line->getAttributes()]);
        }, 3);
    }

    public function updateAmount(User $actor, string $level, int $id, int $version, string $value): void
    {
        $this->allowed($actor);
        $amount = Decimal::parse($value);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Anggaran harus lebih dari nol.']);
        }
        DB::transaction(function () use ($actor, $level, $id, $version, $amount): void {
            if ($level === 'budget') {
                $record = FinanceBudget::lockForUpdate()->findOrFail($id);
                $allocated = $record->scopes()->sum('amount_minor');
            } elseif ($level === 'scope') {
                $budgetId = FinanceBudgetScope::findOrFail($id)->budget_id;
                $budget = FinanceBudget::lockForUpdate()->findOrFail($budgetId);
                $record = $budget->scopes()->lockForUpdate()->findOrFail($id);
                $allocated = $record->lines()->sum('amount_minor');
                $other = $budget->scopes()->whereKeyNot($id)->sum('amount_minor');
                if ($other + $amount > $budget->amount_minor) {
                    throw ValidationException::withMessages(['amount' => 'Total alokasi HO dan project melebihi anggaran utama.']);
                }
            } elseif ($level === 'line') {
                $scopeId = FinanceBudgetLine::findOrFail($id)->scope_id;
                $scope = FinanceBudgetScope::lockForUpdate()->findOrFail($scopeId);
                $record = $scope->lines()->lockForUpdate()->findOrFail($id);
                $allocated = 0;
                $used = app(BudgetLedger::class)->lineSummary($record)['used_minor'];
                $other = $scope->lines()->whereKeyNot($id)->sum('amount_minor');
                if ($other + $amount > $scope->amount_minor) {
                    throw ValidationException::withMessages(['amount' => 'Total alokasi departemen melebihi anggaran HO atau project.']);
                }
                if ($amount < $used) {
                    throw ValidationException::withMessages(['amount' => 'Anggaran tidak boleh lebih kecil dari realisasi dan komitmen yang sudah tercatat.']);
                }
            } else {
                abort(404);
            }
            if ($record->version !== $version) {
                throw ValidationException::withMessages(['amount' => 'Anggaran berubah. Buka kembali data sebelum menyimpan.']);
            }
            if ($amount < $allocated) {
                throw ValidationException::withMessages(['amount' => 'Anggaran tidak boleh lebih kecil dari alokasi yang sudah dibuat.']);
            }
            $before = $record->getAttributes();
            $record->amount_minor = $amount;
            $record->version++;
            if ($level === 'budget') {
                $record->updated_by = $actor->id;
            }
            $record->save();
            app(Workflow::class)->audit($actor, $record, 'ubah_anggaran', ['before' => $before, 'after' => $record->getAttributes()]);
        }, 3);
    }
}
