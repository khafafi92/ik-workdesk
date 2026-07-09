<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkTaskFinding;

class WorkTaskFindingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(
        User $user,
        WorkTaskFinding $workTaskFinding
    ): bool {
        return true;
    }

    public function create(User $user): bool
    {
        /*
         * Department pemilik Work Log akan diperiksa
         * di FindingsRelationManager.
         */
        return true;
    }

    public function update(
        User $user,
        WorkTaskFinding $workTaskFinding
    ): bool {
        $workTaskFinding->loadMissing('workTask.ticket');

        $isReviewerDepartment = $user->belongsToDepartment(
            $workTaskFinding->workTask?->department_id
        );

        $isRequester =
            (int) $user->employee_id ===
            (int) $workTaskFinding->workTask?->ticket?->employee_id;

        /*
         * Reviewer boleh mengelola finding.
         * Requester boleh mengisi response melalui halaman ticket.
         */
        return $isReviewerDepartment || $isRequester;
    }

    public function delete(
        User $user,
        WorkTaskFinding $workTaskFinding
    ): bool {
        $workTaskFinding->loadMissing('workTask');

        return $user->belongsToDepartment(
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
}