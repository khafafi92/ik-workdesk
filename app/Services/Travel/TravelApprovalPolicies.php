<?php

namespace App\Services\Travel;

use App\Models\TravelApprovalPolicy;
use App\Models\TravelApprovalPolicyEvent;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TravelApprovalPolicies
{
    public const KINDS = ['ALL' => 'SPD and LPJ', 'SPD' => 'SPD', 'LPJ' => 'LPJ'];

    public const TRAVEL_TYPES = ['HO' => 'HO (Finance review)', 'PROJECT' => 'Project (Project Manager review)'];

    public static function reviewerLabel(string $travelType): string
    {
        return strtoupper($travelType) === 'PROJECT' ? 'Manager Project' : 'Finance';
    }

    public static function defaults(): array
    {
        return ['company' => 'APCA', 'department_id' => '', 'document_kind' => 'ALL', 'travel_type' => 'HO',
            'hr_id' => '', 'finance_id' => '', 'supervisor_id' => '', 'is_active' => true];
    }

    public function installed(): bool
    {
        return Schema::hasTable('travel_approval_policies');
    }

    public function save(User $actor, array $input, ?int $id, int $version): TravelApprovalPolicy
    {
        abort_unless(TravelAccess::has($actor, 'travel.configure'), 403);
        $this->assertInstalled();
        $data = Validator::make($input, ['company' => ['required', 'in:APCA,KPMOG'],
            'department_id' => ['required', 'integer', 'exists:departments,id'], 'document_kind' => ['required', 'in:ALL,SPD,LPJ'],
            'travel_type' => ['required', 'in:HO,PROJECT'],
            'hr_id' => ['required', 'integer', 'exists:users,id'], 'finance_id' => ['required', 'integer', 'different:hr_id', 'exists:users,id'],
            'supervisor_id' => ['nullable', 'integer', 'different:hr_id', 'different:finance_id', 'exists:users,id'],
            'is_active' => ['required', 'boolean']], ['required' => 'Select :attribute.'], [
                'company' => 'company', 'department_id' => 'department', 'document_kind' => 'document type', 'travel_type' => 'travel type',
                'hr_id' => 'HRGA / HR Manager', 'finance_id' => 'reviewer',
            ])->validate();
        $data['department_id'] = (int) $data['department_id'];
        $data['hr_id'] = (int) $data['hr_id'];
        $data['finance_id'] = (int) $data['finance_id'];
        $data['supervisor_id'] = filled($data['supervisor_id'] ?? null) ? (int) $data['supervisor_id'] : null;
        $assignedAccess = TravelAccess::canArrangeDocument($actor);
        foreach (['hr', 'finance', 'supervisor'] as $role) {
            if ($data[$role.'_id']) {
                $this->signer($data[$role.'_id'], $role, $assignedAccess);
            }
        }
        try {
            return DB::transaction(function () use ($actor, $data, $id, $version, $assignedAccess): TravelApprovalPolicy {
                $policy = $id ? TravelApprovalPolicy::lockForUpdate()->findOrFail($id) : null;
                if (($policy?->version ?? 0) !== $version) {
                    throw ValidationException::withMessages(['rule' => 'The rules have changed, reopen before saving.']);
                }
                if ($policy && $policy->only(['company', 'department_id', 'document_kind', 'travel_type']) !== array_intersect_key($data, array_flip(['company', 'department_id', 'document_kind', 'travel_type']))) {
                    throw ValidationException::withMessages(['rule' => 'The scope of rules cannot be changed when Editing. Use Copy to create new coverage.']);
                }
                $before = $policy?->getAttributes();
                $policy ??= new TravelApprovalPolicy;
                $policy->fill($data);
                $policy->version = $version + 1;
                $policy->save();
                $this->audit($actor, $policy, 'saved', ['before' => $before, 'after' => $policy->getAttributes(), 'assigned_access' => $assignedAccess]);

                return $policy;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['rule' => 'The rules for this company, department, type of document, and type of travel already exist. Use Edit or select other coverage.']);
        }
    }

    public function delete(User $actor, int $id, int $version): void
    {
        abort_unless(TravelAccess::has($actor, 'travel.configure'), 403);
        $this->assertInstalled();
        DB::transaction(function () use ($actor, $id, $version): void {
            $policy = TravelApprovalPolicy::lockForUpdate()->findOrFail($id);
            if ($policy->version !== $version) {
                throw ValidationException::withMessages(['rule' => 'The rules have changed. Reload before deleting.']);
            }
            $this->audit($actor, $policy, 'deleted', ['before' => $policy->getAttributes()]);
            $policy->delete();
        });
    }

    public function resolve(string $company, int $departmentId, string $kind, array $employee, int $creatorId, bool $lock = false, string $travelType = 'HO'): array
    {
        $this->assertInstalled();
        $travelType = strtoupper($travelType);
        if (! array_key_exists($travelType, self::TRAVEL_TYPES)) {
            throw ValidationException::withMessages(['approval_policy' => 'Unknown SPD travel type.']);
        }
        $query = TravelApprovalPolicy::where('company', $company)->where('department_id', $departmentId)->where('is_active', true)
            ->where('travel_type', $travelType)->whereIn('document_kind', [strtoupper($kind), 'ALL'])
            ->orderByRaw('CASE WHEN document_kind = ? THEN 0 ELSE 1 END', [strtoupper($kind)]);
        $policy = ($lock ? $query->lockForUpdate() : $query)->first();
        if (! $policy) {
            throw ValidationException::withMessages(['approval_policy' => 'Master Approval '.strtoupper($kind).' for the ride '.self::TRAVEL_TYPES[$travelType].' contact the administrator.']);
        }
        $ids = ['hr' => $policy->hr_id, 'finance' => $policy->finance_id,
            'supervisor' => $policy->supervisor_id ?: ($employee['supervisor_user_id'] ?? null)];
        if (! $ids['supervisor']) {
            throw ValidationException::withMessages(['approval_policy' => 'The immediate boss is not available, complete Master Employee or the superior establishment on the approval rules.']);
        }
        $authorizer = $this->assignmentAuthorizer($policy);
        $signers = $this->validateSigners($ids, $employee, $creatorId, ! $authorizer, (bool) $authorizer);
        $snapshot = $policy->only(['id', 'version', 'company', 'department_id', 'document_kind', 'travel_type', 'hr_id', 'finance_id', 'supervisor_id']);
        if ($authorizer) {
            $snapshot += ['source' => 'khafafi-master', 'arranged_by' => (int) $authorizer->id, 'assigned_access' => true];
        }

        return ['ids' => array_map('intval', $ids), 'users' => $signers,
            'policy' => $snapshot];
    }

    public function arrange(User $actor, array $ids, array $employee, int $requesterId): array
    {
        abort_unless(TravelAccess::canArrangeDocument($actor), 403);
        Validator::make(['approver_ids' => $ids], ['approver_ids' => ['required', 'array:hr,finance,supervisor'],
            'approver_ids.hr' => ['required', 'integer'], 'approver_ids.finance' => ['required', 'integer'],
            'approver_ids.supervisor' => ['required', 'integer']])->validate();
        $ids = array_map('intval', array_replace(array_fill_keys(array_keys(TravelWorkflow::ROLES), 0), $ids));
        $signers = $this->validateSigners($ids, $employee, $requesterId, false, true);

        return ['ids' => $ids, 'users' => $signers, 'policy' => ['source' => 'khafafi', 'arranged_by' => (int) $actor->id, 'assigned_access' => true]];
    }

    private function validateSigners(array $ids, array $employee, int $creatorId, bool $matchSupervisor, bool $assignedAccess = false): array
    {
        $signers = [];
        foreach ($ids as $role => $id) {
            $user = $this->signer((int) $id, $role, $assignedAccess);
            if (in_array((int) $id, [$creatorId, (int) ($employee['user_id'] ?? 0)], true)) {
                throw ValidationException::withMessages(['approval_policy' => 'Travelers or travel employees should not approve their own documents.']);
            }
            $signers[$role] = $user;
        }
        if (count(array_unique(array_map('intval', $ids))) !== 3) {
            throw ValidationException::withMessages(['approval_policy' => 'H.R., Examiner, and Chief Direct should be different, check on Master Approval and Master Employee.']);
        }
        if ($matchSupervisor && ! empty($employee['supervisor_user_id']) && (int) $ids['supervisor'] !== (int) $employee['supervisor_user_id']) {
            throw ValidationException::withMessages(['approval_policy' => 'The superior should be the direct superior in Master Employee, empty the top\'s setup to use Master Employee automatically.']);
        }

        return $signers;
    }

    public function preview(string $company, int $departmentId, string $kind, array $employee, int $creatorId, string $travelType = 'HO'): array
    {
        try {
            return ['plan' => $this->resolve($company, $departmentId, $kind, $employee, $creatorId, false, $travelType), 'error' => null];
        } catch (ValidationException $exception) {
            return ['plan' => null, 'error' => collect($exception->errors())->flatten()->first()];
        }
    }

    private function assignmentAuthorizer(TravelApprovalPolicy $policy): ?User
    {
        $event = TravelApprovalPolicyEvent::with('actor')->where('policy_id', $policy->id)->where('action', 'saved')->latest('id')->first();
        if (($event?->details['assigned_access'] ?? false) !== true || ! TravelAccess::canArrangeDocument($event?->actor)) {
            return null;
        }
        foreach (['id', 'version', 'company', 'department_id', 'document_kind', 'travel_type', 'hr_id', 'finance_id', 'supervisor_id', 'is_active'] as $field) {
            if ($field === 'travel_type' && ! array_key_exists($field, $event->details['after'] ?? []) && $policy->travel_type === 'HO') {
                continue;
            }
            if (! array_key_exists($field, $event->details['after'] ?? []) || (string) $event->details['after'][$field] !== (string) $policy->{$field}) {
                return null;
            }
        }

        return $event->actor;
    }

    private function signer(int $id, string $role, bool $assignedAccess = false): User
    {
        $user = User::with('employee')->find($id);
        if (! $user || ! $user->isActiveForAccess() || (! $assignedAccess && ! TravelAccess::approver($user))) {
            throw ValidationException::withMessages(['approval_policy' => (TravelWorkflow::ROLES[$role] ?? $role)
                .($assignedAccess ? ' must use an active account.' : ' must use an active account with SPD/LPJ approval access rights. Check Master Approval.')]);
        }
        if ($role === 'hr' && ! TravelAccess::manager($user)) {
            throw ValidationException::withMessages(['approval_policy' => 'HRGA/HR Manager on the rule must have a minimal rank manager.']);
        }

        return $user;
    }

    private function assertInstalled(): void
    {
        if (! $this->installed()) {
            throw ValidationException::withMessages(['approval_policy' => 'Master Approval SPD/LPJ is not set up. Contact administrator.']);
        }
    }

    private function audit(User $actor, TravelApprovalPolicy $policy, string $action, array $details): void
    {
        TravelApprovalPolicyEvent::create(['policy_id' => $policy->id, 'actor_id' => $actor->id, 'action' => $action, 'details' => $details]);
    }
}
