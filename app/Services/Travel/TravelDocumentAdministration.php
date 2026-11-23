<?php

namespace App\Services\Travel;

use App\Models\TravelDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TravelDocumentAdministration
{
    public function requesters(): Builder
    {
        return User::whereHas('employee', fn ($query) => $query->where('is_active', true)->whereNotNull('department_id'))
            ->with('employee.department')->orderBy('name');
    }

    public function normalize(User $actor, array $input, ?TravelDocument $document): array
    {
        $stored = $document?->data ?? [];
        $previousRequester = (int) ($stored['requester_id'] ?? $document?->created_by ?? $actor->id);
        $requesterId = $input['requester_id'] ?? $previousRequester;
        Validator::make(['requester_id' => $requesterId], ['requester_id' => ['required', 'integer']])->validate();
        $requesterId = (int) $requesterId;
        $canArrange = TravelAccess::canArrangeDocument($actor);
        abort_unless($requesterId === $previousRequester || $canArrange, 403);
        $requester = User::with('employee.department')->find($requesterId);
        if (! $requester || ! $requester->isActiveForAccess() || ($canArrange && (! $requester->employee || ! $requester->employee->department_id))) {
            throw ValidationException::withMessages(['requester_id' => 'Select the active driver/juggler connected to Master Employee and department.']);
        }
        $oldOverride = $stored['approval_override'] ?? null;
        $enabled = $input['arrange_approvals'] ?? (bool) $oldOverride;
        Validator::make(['arrange_approvals' => $enabled], ['arrange_approvals' => ['required', 'boolean']])->validate();
        $override = null;
        if ($canArrange) {
            if ($enabled) {
                $ids = $input['approver_ids'] ?? [];
                Validator::make(['approver_ids' => $ids], ['approver_ids' => ['array:hr,finance,supervisor'],
                    'approver_ids.*' => ['nullable', 'integer']])->validate();
                $override = ['arranged_by' => (int) $actor->id, 'ids' => array_replace(array_fill_keys(array_keys(TravelWorkflow::ROLES), ''), $ids)];
            }
        } else {
            abort_unless((bool) $enabled === (bool) $oldOverride, 403);
            if ($oldOverride && isset($input['approver_ids'])) {
                foreach (TravelWorkflow::ROLES as $role => $label) {
                    abort_unless((string) ($input['approver_ids'][$role] ?? '') === (string) ($oldOverride['ids'][$role] ?? ''), 403);
                }
            }
            $override = $oldOverride;
        }

        return ['requester_id' => $requesterId,
            'requester_snapshot' => ['name' => $requester->name, 'position' => $requester->employee?->position],
            'approval_override' => $override];
    }

    public function plan(string $company, int $departmentId, string $kind, array $employee, int $requesterId, ?array $override, bool $lock = false, string $travelType = 'HO'): array
    {
        if (! $override) {
            return app(TravelApprovalPolicies::class)->resolve($company, $departmentId, $kind, $employee, $requesterId, $lock, $travelType);
        }
        $authorizer = User::find($override['arranged_by'] ?? 0);
        if (! TravelAccess::canArrangeDocument($authorizer)) {
            throw ValidationException::withMessages(['approval_policy' => 'Checker adjustments need to be re-ordained by the active Khafafi as admin sys.']);
        }

        return app(TravelApprovalPolicies::class)->arrange($authorizer, $override['ids'] ?? [], $employee, $requesterId);
    }

    public function preview(string $company, int $departmentId, string $kind, array $employee, int $requesterId, ?array $override, string $travelType = 'HO'): array
    {
        try {
            return ['plan' => $this->plan($company, $departmentId, $kind, $employee, $requesterId, $override, false, $travelType), 'error' => null];
        } catch (ValidationException $exception) {
            return ['plan' => null, 'error' => collect($exception->errors())->flatten()->first()];
        }
    }
}
