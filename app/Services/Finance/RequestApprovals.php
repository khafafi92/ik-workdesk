<?php

namespace App\Services\Finance;

use App\Models\FinanceRequest;
use App\Models\FinanceRequestPolicy;
use App\Models\Scm\ApprovalPolicy;
use App\Models\Scm\ApprovalRoute;
use App\Models\User;
use App\Services\Scm\Access;
use App\Services\Scm\ApprovalPolicies;
use App\Services\Scm\Approvals;
use App\Services\Scm\Workflow;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RequestApprovals
{
    private const STEPS = [
        'payment' => ['Approved'],
        'reimbursement' => ['Approved', 'Checked', 'Received'],
        'expense_report' => ['Acknowledged', 'Crosschecked', 'Approved'],
    ];

    public static function steps(string $kind, string $company): array
    {
        return $kind === 'payment' && $company === 'KPMOG' ? self::STEPS['expense_report'] : self::STEPS[$kind];
    }

    public static function canConfigure(?User $user): bool
    {
        return Access::has($user, 'scm.configure');
    }

    public static function companies(string $kind): array
    {
        return match ($kind) {
            'expense_report' => ['KPMOG'],
            'reimbursement' => ['APCA'],
            default => FinanceRequest::COMPANIES,
        };
    }

    public function save(User $actor, string $company, string $kind, array $reviewers, int $version): void
    {
        abort_unless(self::canConfigure($actor), 403);
        abort_unless(isset(self::STEPS[$kind]) && in_array($company, self::companies($kind), true), 422);
        $reviewers = Validator::make(['reviewers' => $reviewers], [
            'reviewers' => 'required|array|list|size:'.count(self::steps($kind, $company)),
            'reviewers.*' => 'required|integer|distinct|exists:users,id',
        ], ['reviewers.*.distinct' => 'Pilih akun yang berbeda untuk setiap tahap.'])->validate()['reviewers'];
        $reviewers = array_values(array_map('intval', $reviewers));
        $users = User::with('employee')->findMany($reviewers)->keyBy('id');
        foreach ($reviewers as $step => $id) {
            if (! $users->get($id)?->isActiveForAccess()) {
                throw ValidationException::withMessages(["reviewers.$step" => 'Pilih akun yang masih aktif.']);
            }
        }

        try {
            DB::transaction(function () use ($actor, $company, $kind, $reviewers, $version): void {
                $policy = FinanceRequestPolicy::where(compact('company', 'kind'))->lockForUpdate()->first();
                if (($policy?->version ?? 0) !== $version) {
                    throw ValidationException::withMessages(['policy' => 'Aturan sudah diperbarui. Muat ulang sebelum menyimpan.']);
                }
                $before = $policy?->reviewers;
                $policy ??= new FinanceRequestPolicy(compact('company', 'kind'));
                $policy->fill(['reviewers' => $reviewers, 'version' => $version + 1])->save();
                app(Workflow::class)->audit($actor, $policy, 'master_approval_finance_request', [
                    'company' => $company, 'kind' => $kind, 'before' => $before, 'after' => $reviewers, 'version' => $policy->version,
                ]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['policy' => 'Aturan sudah diperbarui. Muat ulang sebelum menyimpan.']);
        }
    }

    public function plan(FinanceRequest $request): array
    {
        $policy = FinanceRequestPolicy::where(['company' => $request->company, 'kind' => $request->kind])->first();
        $steps = self::steps($request->kind, $request->company);
        if (! $policy || count($policy->reviewers) !== count($steps)) {
            if ($scmPlan = $this->planFromScm($request)) {
                return $scmPlan;
            }

            throw ValidationException::withMessages(['request' => $policy
                ? 'Lengkapi semua tahap approval di Master Approval SCM sebelum pengajuan dikirim.'
                : 'Aturan approval belum tersedia di Master Approval SCM untuk perusahaan dan departemen ini. Hubungi pengelola Master Approval.']);
        }
        $users = User::with('employee')->findMany($policy->reviewers)->keyBy('id');

        return array_map(function ($label, $step) use ($request, $users, $policy): array {
            $user = $users->get($policy->reviewers[$step] ?? null);
            if (! $user?->isActiveForAccess() || (int) $user->id === $request->requesterId()) {
                throw ValidationException::withMessages(['request' => "Tahap {$label} memerlukan approver aktif yang berbeda dari pemohon. Hubungi pengelola Master Approval."]);
            }

            return ['step' => $step + 1, 'label' => $label, 'user_id' => $user->id, 'reviewer_name' => $user->name];
        }, $steps, array_keys($steps));
    }

    private function planFromScm(FinanceRequest $request): ?array
    {
        if (! $request->company || ! $request->department_id) {
            return null;
        }

        $high = (int) ($request->total_minor ?? 0) > Approvals::LIMIT_MINOR;
        $source = $request->procurement;
        $policy = app(ApprovalPolicies::class)->find($request->company, (int) $request->department_id, $source?->need_type ?? 'HO', $source ? 'PR' : 'ALL');
        if (! $source) {
            $policy ??= ApprovalPolicy::where('company', $request->company)
                ->where('department_id', (int) $request->department_id)
                ->first();
        }

        $steps = [];

        if ($policy && ! $policy->uses_legacy_routing) {
            $approverId = ($high && $policy->use_amount_escalation && $policy->high_approver_id)
                ? $policy->high_approver_id
                : $policy->approver_id;

            if ($policy->supervisor_id && (int) $policy->supervisor_id !== $request->requesterId()) {
                $steps[] = ['label' => 'Supervisor', 'user_id' => (int) $policy->supervisor_id];
            }
            if ($policy->acknowledger_id && (int) $policy->acknowledger_id !== $request->requesterId()) {
                $steps[] = ['label' => 'Acknowledged', 'user_id' => (int) $policy->acknowledger_id];
            }
            $crosscheckRequired = $policy->crosschecker_id
                && ($policy->crosscheck_mode === 'always'
                    || ($policy->crosscheck_mode === 'above_limit' && $high));
            if ($crosscheckRequired && (int) $policy->crosschecker_id !== $request->requesterId()) {
                $steps[] = ['label' => 'Crosschecked', 'user_id' => (int) $policy->crosschecker_id];
            }
            if ($approverId) {
                $steps[] = ['label' => 'Approved', 'user_id' => (int) $approverId];
            }
        } elseif ($route = ApprovalRoute::where('company', $request->company)->where('department_id', (int) $request->department_id)->first()) {
            $approverId = ($high && $route->cbo3_id) ? $route->cbo3_id : $route->cbo_id;

            if ($route->acknowledger_id && (int) $route->acknowledger_id !== $request->requesterId()) {
                $steps[] = ['label' => 'Acknowledged', 'user_id' => (int) $route->acknowledger_id];
            }
            if ($route->cbo_id && (int) $route->cbo_id !== (int) $approverId && (int) $route->cbo_id !== $request->requesterId()) {
                $steps[] = ['label' => 'Crosschecked', 'user_id' => (int) $route->cbo_id];
            }
            if ($approverId) {
                $steps[] = ['label' => 'Approved', 'user_id' => (int) $approverId];
            }
        } else {
            return null;
        }

        if ($steps === []) {
            return null;
        }

        $users = User::with('employee')->findMany(array_column($steps, 'user_id'))->keyBy('id');
        $resolved = [];

        foreach ($steps as $index => $step) {
            $user = $users->get($step['user_id']);
            if (! $user?->isActiveForAccess() || (int) $user->id === $request->requesterId()) {
                throw ValidationException::withMessages([
                    'request' => "Tahap {$step['label']} di Master Approval SCM memerlukan approver aktif yang berbeda dari pemohon.",
                ]);
            }

            $resolved[] = [
                'step' => $index + 1,
                'label' => $step['label'],
                'user_id' => $user->id,
                'reviewer_name' => $user->name,
            ];
        }

        return $resolved;
    }
}
