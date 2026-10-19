<?php

namespace App\Policies;

use App\Enums\ItAssetLifecycle;
use App\Enums\ItInspectionStatus;
use App\Enums\ItPermission;
use App\Models\ItDevice;
use App\Models\ItInspection;
use App\Models\User;
use App\Services\ItInfrastructure\ItInfrastructureAccess;

class ItInspectionPolicy
{
    public function viewAny(User $user): bool
    {
        return ItInfrastructureAccess::hasAny($user, ItPermission::InspectionsView, ItPermission::InspectionsCreate, ItPermission::InspectionsManage, ItPermission::FollowUpsManage);
    }

    public function view(User $user, ItInspection $inspection): bool
    {
        return $this->viewAny($user) && $user->canAccessDepartment($inspection->managing_department_id);
    }

    public function create(User $user): bool
    {
        return ItInfrastructureAccess::hasAny($user, ItPermission::InspectionsCreate, ItPermission::InspectionsManage) && $user->accessibleDepartmentIds() !== [];
    }

    public function inspectDevice(User $user, ItDevice $device): bool
    {
        return ! $device->trashed() && $device->is_active && $device->lifecycle_status !== ItAssetLifecycle::Disposed
            && ItInfrastructureAccess::allows($user, $device->managing_department_id, ItPermission::InspectionsCreate, ItPermission::InspectionsManage);
    }

    public function update(User $user, ItInspection $inspection): bool
    {
        if ($inspection->status !== ItInspectionStatus::Draft || ! $this->view($user, $inspection)) {
            return false;
        }

        return ItInfrastructureAccess::hasAny($user, ItPermission::InspectionsManage)
            || ((int) $inspection->inspector_id === (int) $user->id && ItInfrastructureAccess::hasAny($user, ItPermission::InspectionsCreate));
    }

    public function finalize(User $user, ItInspection $inspection): bool
    {
        return $this->update($user, $inspection);
    }

    public function viewReports(User $user): bool
    {
        return ItInfrastructureAccess::hasAny($user, ItPermission::ReportsView, ItPermission::ReportsExport);
    }

    public function export(User $user): bool
    {
        return ItInfrastructureAccess::hasAny($user, ItPermission::ReportsExport);
    }

    public function delete(User $user, ItInspection $inspection): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, ItInspection $inspection): bool
    {
        return false;
    }

    public function forceDelete(User $user, ItInspection $inspection): bool
    {
        return false;
    }
}
