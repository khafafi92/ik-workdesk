<?php

namespace App\Policies;

use App\Enums\ItFollowUpStatus;
use App\Enums\ItInspectionStatus;
use App\Enums\ItPermission;
use App\Models\ItInspectionFollowUp;
use App\Models\ItInspectionResult;
use App\Models\User;
use App\Services\ItInfrastructure\ItInfrastructureAccess;

class ItInspectionFollowUpPolicy
{
    public function viewAny(User $user): bool
    {
        return ItInfrastructureAccess::hasAny($user, ItPermission::FollowUpsManage);
    }

    public function view(User $user, ItInspectionFollowUp $followUp): bool
    {
        $inspection = $followUp->inspection()->firstOrFail();

        return (new ItInspectionPolicy)->update($user, $inspection)
            || ItInfrastructureAccess::allows($user, $inspection->managing_department_id, ItPermission::FollowUpsManage);
    }

    public function createForResult(User $user, ItInspectionResult $result): bool
    {
        $inspection = $result->inspection()->firstOrFail();

        return $result->condition->isFinding()
            && ((new ItInspectionPolicy)->update($user, $inspection)
                || ItInfrastructureAccess::allows($user, $inspection->managing_department_id, ItPermission::FollowUpsManage));
    }

    public function update(User $user, ItInspectionFollowUp $followUp): bool
    {
        return $followUp->status !== ItFollowUpStatus::Resolved && $this->view($user, $followUp);
    }

    public function resolve(User $user, ItInspectionFollowUp $followUp): bool
    {
        return $this->update($user, $followUp);
    }

    public function delete(User $user, ItInspectionFollowUp $followUp): bool
    {
        return $followUp->inspection()->firstOrFail()->status === ItInspectionStatus::Draft && $this->view($user, $followUp);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, ItInspectionFollowUp $followUp): bool
    {
        return false;
    }

    public function forceDelete(User $user, ItInspectionFollowUp $followUp): bool
    {
        return false;
    }
}
