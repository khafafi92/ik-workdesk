<?php

namespace App\Policies;

use App\Enums\ItInspectionStatus;
use App\Enums\ItPermission;
use App\Models\ItDailyMonitoring;
use App\Models\User;
use App\Services\ItInfrastructure\ItDailyMonitoringService;
use App\Services\ItInfrastructure\ItInfrastructureAccess;

class ItDailyMonitoringPolicy
{
    public function viewAny(User $user): bool
    {
        return ItInfrastructureAccess::hasAny($user, ItPermission::InspectionsView, ItPermission::InspectionsCreate, ItPermission::InspectionsManage, ItPermission::ReportsView, ItPermission::ReportsExport);
    }

    public function view(User $user, ItDailyMonitoring $record): bool
    {
        return $this->viewAny($user) && $user->canAccessDepartment($record->managing_department_id)
            && ($record->status === ItInspectionStatus::Finalized || ItInfrastructureAccess::hasAny($user, ItPermission::InspectionsView, ItPermission::InspectionsCreate, ItPermission::InspectionsManage));
    }

    public function create(User $user): bool
    {
        $departmentId = ItDailyMonitoringService::departmentId();

        return $departmentId !== null && ItInfrastructureAccess::allows($user, $departmentId, ItPermission::InspectionsCreate, ItPermission::InspectionsManage);
    }

    public function update(User $user, ItDailyMonitoring $record): bool
    {
        return (int) $record->managing_department_id === ItDailyMonitoringService::departmentId()
            && $record->status === ItInspectionStatus::Draft && $this->view($user, $record)
            && (ItInfrastructureAccess::hasAny($user, ItPermission::InspectionsManage)
                || ((int) $record->inspector_id === (int) $user->id && ItInfrastructureAccess::hasAny($user, ItPermission::InspectionsCreate)));
    }

    public function delete(User $user, ItDailyMonitoring $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
