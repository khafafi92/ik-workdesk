<?php

namespace App\Services\Scm;

use App\Models\Scm\ApprovalPolicy;
use App\Models\Scm\ApprovalRoute;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApprovalPolicies
{
    public const NEED_TYPES = ['HO' => 'Kebutuhan HO', 'PROJECT' => 'Kebutuhan Project'];

    public const KINDS = ['ALL' => 'PR dan SR', 'PR' => 'PR', 'SR' => 'SR'];

    public const CROSSCHECK_MODES = ['always' => 'Wajib', 'above_limit' => 'Wajib di atas Rp5 juta, opsional lainnya', 'optional' => 'Opsional'];

    public static function defaults(): array
    {
        return ['company' => 'APCA', 'department_id' => '', 'need_type' => 'HO', 'request_kind' => 'ALL',
            'supervisor_id' => '', 'acknowledger_id' => '', 'crosschecker_id' => '', 'approver_id' => '',
            'use_amount_escalation' => true, 'high_approver_id' => '', 'crosscheck_mode' => 'above_limit', 'version' => 0];
    }

    public function find(string $company, int $departmentId, string $needType, string $kind): ?ApprovalPolicy
    {
        return ApprovalPolicy::where('company', $company)->where('department_id', $departmentId)->where('need_type', $needType)
            ->whereIn('request_kind', [$kind, 'ALL'])->orderByRaw('CASE WHEN request_kind = ? THEN 0 ELSE 1 END', [$kind])->first();
    }

    public function isCbo(User $user): bool
    {
        $user->loadMissing(['employee', 'roles']);

        return $user->employee?->position_level === 'cbo'
            || preg_match('/\bCBO\b|Chief\s+Business\s+Officer/i', $user->employee?->position ?? '') === 1
            || $user->roles->contains(fn ($role) => $role->is_active && $role->code === 'cbo')
            || ApprovalRoute::where('cbo_id', $user->id)->exists();
    }

    private function validateSigner(User $user, string $needType): void
    {
        if (! $user->isActiveForAccess()) {
            throw ValidationException::withMessages(['scm' => 'Penandatangan harus memakai akun aktif.']);
        }
        if ($needType === 'PROJECT' && $this->isCbo($user)) {
            throw ValidationException::withMessages(['scm' => 'CBO hanya untuk kebutuhan HO dan tidak dapat ditetapkan pada approval Project.']);
        }
    }

    private function requireHoCbo(?User $crosschecker, User $approver): void
    {
        if (! $this->isCbo($approver) && (! $crosschecker || ! $this->isCbo($crosschecker))) {
            throw ValidationException::withMessages(['scm' => 'Kebutuhan HO harus melibatkan CBO pada Crosschecked by atau Approved by. Petakan jabatan CBO pada master Employee.']);
        }
    }

    public function save(User $actor, array $data): ApprovalPolicy
    {
        abort_unless(Access::has($actor, 'scm.configure'), 403);
        $data = Validation::make($data, [
            'company' => ['required', Rule::in(array_keys(config('scm.companies')))],
            'department_id' => 'required|integer|exists:departments,id',
            'need_type' => ['required', Rule::in(array_keys(self::NEED_TYPES))],
            'request_kind' => ['required', Rule::in(array_keys(self::KINDS))],
            'supervisor_id' => 'nullable|integer|exists:users,id', 'acknowledger_id' => 'nullable|integer|exists:users,id',
            'crosschecker_id' => 'nullable|integer|exists:users,id', 'approver_id' => 'required|integer|exists:users,id',
            'use_amount_escalation' => 'required|boolean', 'high_approver_id' => 'nullable|integer|exists:users,id',
            'crosscheck_mode' => ['required', Rule::in(array_keys(self::CROSSCHECK_MODES))], 'version' => 'required|integer|min:0',
        ])->validate();
        if (! $data['use_amount_escalation']) {
            $data['high_approver_id'] = null;
        }
        foreach (['supervisor_id', 'acknowledger_id', 'crosschecker_id', 'approver_id', 'high_approver_id'] as $field) {
            if (! empty($data[$field])) {
                $this->validateSigner(User::findOrFail($data[$field]), $data['need_type']);
            }
        }
        if ($data['need_type'] === 'HO') {
            $crosschecker = ! empty($data['crosschecker_id']) ? User::findOrFail($data['crosschecker_id']) : null;
            $this->requireHoCbo($crosschecker, User::findOrFail($data['approver_id']));
            if ($data['use_amount_escalation'] && ! empty($data['high_approver_id'])) {
                $this->requireHoCbo($crosschecker, User::findOrFail($data['high_approver_id']));
            }
        }

        return DB::transaction(function () use ($actor, $data): ApprovalPolicy {
            $policy = ApprovalPolicy::where(collect($data)->only(['company', 'department_id', 'need_type', 'request_kind'])->all())->lockForUpdate()->first();
            if (($policy?->version ?? 0) !== $data['version']) {
                throw ValidationException::withMessages(['scm' => $policy && $data['version'] === 0
                    ? 'Master untuk perusahaan, divisi, dan jenis kebutuhan ini sudah ada. Gunakan Edit pada daftar master atau pilih cakupan lain.'
                    : 'Master approval berubah. Buka ulang sebelum menyimpan.']);
            }
            unset($data['version']);
            $before = $policy?->getAttributes();
            $policy ??= new ApprovalPolicy;
            $policy->fill([...$data, 'uses_legacy_routing' => false, 'legacy_route_id' => null]);
            $policy->version = ($policy->version ?? 0) + 1;
            $policy->save();
            app(Workflow::class)->audit($actor, $policy, 'pengaturan_master_approval', ['before' => $before, 'after' => $policy->getAttributes()]);

            return $policy;
        });
    }

    public function syncLegacy(ApprovalRoute $route): void
    {
        $policy = ApprovalPolicy::where('company', $route->company)->where('department_id', $route->department_id)
            ->where('need_type', 'HO')->where('request_kind', 'ALL')->first();
        if ($policy && ! $policy->uses_legacy_routing && $policy->legacy_route_id === null) {
            return;
        }
        $legacyRouting = $policy?->uses_legacy_routing ?? true;
        $policy ??= new ApprovalPolicy;
        $policy->fill(['company' => $route->company, 'department_id' => $route->department_id, 'need_type' => 'HO', 'request_kind' => 'ALL',
            'acknowledger_id' => $route->acknowledger_id, 'crosschecker_id' => $route->cbo_id, 'approver_id' => $route->cbo_id,
            'use_amount_escalation' => true, 'high_approver_id' => $route->cbo3_id, 'crosscheck_mode' => 'above_limit',
            'uses_legacy_routing' => $legacyRouting, 'legacy_route_id' => $route->id, 'version' => $route->version])->save();
    }

    public function plan(User $actor, string $company, int $departmentId, bool $high, string $needType, string $kind): ?array
    {
        $policy = $this->find($company, $departmentId, $needType, $kind);
        if (! $policy || $policy->uses_legacy_routing) {
            if ($needType === 'HO') {
                return null;
            }
            throw ValidationException::withMessages(['scm' => 'Master approval Project untuk perusahaan, divisi, dan jenis PR/SR ini belum diatur.']);
        }
        $policy->load(['supervisor', 'acknowledger', 'crosschecker', 'approver', 'highApprover']);
        if ($high && $policy->use_amount_escalation && ! $policy->high_approver_id) {
            throw ValidationException::withMessages(['scm' => 'Approved by untuk pengajuan di atas Rp5 juta belum ditetapkan pada master approval.']);
        }
        $approver = $high && $policy->use_amount_escalation ? $policy->highApprover : $policy->approver;
        foreach (array_filter([$policy->supervisor, $policy->acknowledger, $policy->crosschecker, $approver]) as $signer) {
            $this->validateSigner($signer, $needType);
        }
        if ($needType === 'HO') {
            $this->requireHoCbo($policy->crosschecker, $approver);
        }

        return ['is_custom' => true, 'route' => $policy, 'supervisor' => $policy->supervisor, 'acknowledger' => $policy->acknowledger,
            'crosschecker' => $policy->crosschecker, 'approver' => $approver,
            'crosscheck_required' => $policy->crosscheck_mode === 'always' || ($policy->crosscheck_mode === 'above_limit' && $high)
                || ($needType === 'HO' && ! $this->isCbo($approver)),
            'manager_request' => $policy->acknowledger_id === $actor->id];
    }
}
