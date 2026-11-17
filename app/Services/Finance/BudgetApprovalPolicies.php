<?php

namespace App\Services\Finance;

use App\Models\FinanceBudgetApprovalPolicy;
use App\Models\User;
use App\Services\Scm\Access;
use App\Services\Scm\Workflow;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BudgetApprovalPolicies
{
    public static function defaults(): array
    {
        return ['company' => 'KPMOG', 'need_type' => 'HO', 'finance_manager_id' => '', 'cbo_id' => '', 'coo_id' => '', 'version' => 0];
    }

    public function save(User $actor, array $input): FinanceBudgetApprovalPolicy
    {
        abort_unless(Access::has($actor, 'scm.configure'), 403);
        if (! Schema::hasTable('finance_budget_approval_policies')) {
            throw ValidationException::withMessages(['scm' => 'Master persetujuan anggaran belum disiapkan di database.']);
        }
        $data = Validator::make($input, [
            'company' => ['required', Rule::in(array_keys(config('scm.companies')))],
            'need_type' => ['required', Rule::in(['HO', 'PROJECT'])],
            'finance_manager_id' => 'required|integer|exists:users,id',
            'cbo_id' => 'required|integer|different:finance_manager_id|exists:users,id',
            'coo_id' => 'required|integer|different:finance_manager_id|different:cbo_id|exists:users,id',
            'version' => 'required|integer|min:0',
        ])->validate();
        if (User::whereIn('id', [$data['finance_manager_id'], $data['cbo_id'], $data['coo_id']])->get()->contains(fn ($user) => ! $user->isActiveForAccess())) {
            throw ValidationException::withMessages(['scm' => 'Penandatangan harus memakai akun aktif.']);
        }
        try {
            return DB::transaction(function () use ($actor, $data): FinanceBudgetApprovalPolicy {
                $policy = FinanceBudgetApprovalPolicy::where('company', $data['company'])->where('need_type', $data['need_type'])->lockForUpdate()->first();
                if (($policy?->version ?? 0) !== (int) $data['version']) {
                    throw ValidationException::withMessages(['scm' => 'Aturan persetujuan anggaran berubah. Buka ulang sebelum menyimpan.']);
                }
                $before = $policy?->getAttributes();
                $policy ??= new FinanceBudgetApprovalPolicy;
                $policy->fill(collect($data)->except('version')->all());
                $policy->version = ($policy->version ?? 0) + 1;
                $policy->save();
                app(Workflow::class)->audit($actor, $policy, 'master_approval_anggaran', ['before' => $before, 'after' => $policy->getAttributes()]);

                return $policy;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['scm' => 'Aturan persetujuan anggaran sudah berubah. Buka ulang sebelum menyimpan.']);
        }
    }
}
