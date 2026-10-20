<?php

namespace App\Policies;

use App\Enums\ItAssetLifecycle;
use App\Enums\ItObjectKind;
use App\Enums\ItPermission;
use App\Models\ItDevice;
use App\Models\User;
use App\Services\ItInfrastructure\ItInfrastructureAccess;

class ItDevicePolicy
{
    public function viewAny(User $user): bool
    {
        return ItInfrastructureAccess::hasAny($user, ItPermission::DevicesView, ItPermission::DevicesManage);
    }

    public function view(User $user, ItDevice $device): bool
    {
        return ItInfrastructureAccess::allows($user, $device->managing_department_id, ItPermission::DevicesView, ItPermission::DevicesManage);
    }

    public function create(User $user): bool
    {
        return ItInfrastructureAccess::hasAny($user, ItPermission::DevicesManage) && $user->accessibleDepartmentIds() !== [];
    }

    public function update(User $user, ItDevice $device): bool
    {
        return ! $device->trashed() && ItInfrastructureAccess::allows($user, $device->managing_department_id, ItPermission::DevicesManage);
    }

    public function assignDepartment(User $user, int $departmentId): bool
    {
        return ItInfrastructureAccess::allows($user, $departmentId, ItPermission::DevicesManage);
    }

    public function transfer(User $user, ItDevice $device): bool
    {
        return $this->update($user, $device) && $device->is_active
            && $device->object_kind !== ItObjectKind::Room
            && $device->lifecycle_status !== ItAssetLifecycle::Disposed;
    }

    public function delete(User $user, ItDevice $device): bool
    {
        return $this->update($user, $device);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, ItDevice $device): bool
    {
        return false;
    }

    public function forceDelete(User $user, ItDevice $device): bool
    {
        return false;
    }
}
