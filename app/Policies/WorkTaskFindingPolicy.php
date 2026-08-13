<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkTaskFinding;

class WorkTaskFindingPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasViewPermission($user);
    }

    public function view(
        User $user,
        WorkTaskFinding $workTaskFinding
    ): bool {
        return $this->canAccessFinding($user, $workTaskFinding);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('worklogs.manage');
    }

    public function update(
        User $user,
        WorkTaskFinding $workTaskFinding
    ): bool {
        $workTaskFinding->loadMissing('workTask.ticket');

        $isReviewerDepartment = $user->belongsToDepartment(
            $workTaskFinding->workTask?->department_id
        );

        $isRequester = (int) $user->employee?->id ===
            (int) $workTaskFinding->workTask?->ticket?->employee_id;

        if (
            $workTaskFinding->workTask?->isLegalApprovalLocked()
            && $isReviewerDepartment
            && ! $isRequester
            && ! $user->is_admin
            && ! $user->hasRole('system-admin')
        ) {
            return false;
        }

        /*
         * Reviewer boleh mengelola finding.
         * Requester boleh mengisi response melalui halaman ticket.
         */
        return $this->canAccessFinding($user, $workTaskFinding)
            && ($isReviewerDepartment || $isRequester);
    }

    public function delete(
        User $user,
        WorkTaskFinding $workTaskFinding
    ): bool {
        $workTaskFinding->loadMissing('workTask');

        return ! $workTaskFinding->workTask?->isLegalApprovalLocked()
            && $user->hasPermission('worklogs.manage')
            && $user->belongsToDepartment(
                $workTaskFinding->workTask?->department_id
            );
    }

    public function restore(
        User $user,
        WorkTaskFinding $workTaskFinding
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        WorkTaskFinding $workTaskFinding
    ): bool {
        return false;
    }

    private function hasViewPermission(User $user): bool
    {
        return $user->hasPermission('worklogs.view')
            || $user->hasPermission('worklogs.manage')
            || $user->hasPermission('tickets.view')
            || $user->hasPermission('tickets.manage');
    }

    private function canAccessFinding(
        User $user,
        WorkTaskFinding $finding
    ): bool {
        if (! $this->hasViewPermission($user)) {
            return false;
        }

        if ($user->is_admin || $user->hasRole('system-admin')) {
            return true;
        }

        $finding->loadMissing('workTask.ticket.assignments');
        $workTask = $finding->workTask;

        if (! $workTask) {
            return false;
        }

        if (
            $user->employee
            && (int) $workTask->ticket?->employee_id ===
                (int) $user->employee->id
        ) {
            return true;
        }

        $departmentIds = $user->accessibleDepartmentIds();

        return in_array((int) $workTask->department_id, $departmentIds, true)
            || in_array(
                (int) $workTask->ticket?->requester_department_id,
                $departmentIds,
                true
            )
            || in_array(
                (int) $workTask->ticket?->handler_department_id,
                $departmentIds,
                true
            )
            || $workTask->ticket?->assignments
                ->contains(
                    fn ($assignment): bool => in_array(
                        (int) $assignment->department_id,
                        $departmentIds,
                        true
                    )
                ) === true;
    }
}
