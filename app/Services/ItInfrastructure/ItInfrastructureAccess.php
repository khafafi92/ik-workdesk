<?php

namespace App\Services\ItInfrastructure;

use App\Enums\ItPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ItInfrastructureAccess
{
    public static function hasAny(?User $user, ItPermission ...$permissions): bool
    {
        if (! $user || ! $user->isActiveForAccess()) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission->value)) {
                return true;
            }
        }

        return false;
    }

    public static function allows(User $user, int $departmentId, ItPermission ...$permissions): bool
    {
        return self::hasAny($user, ...$permissions) && $user->canAccessDepartment($departmentId);
    }

    public static function scope(Builder $query, ?User $user, string $departmentColumn, ItPermission ...$permissions): Builder
    {
        if (! self::hasAny($user, ...$permissions)) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->is_admin || $user->hasRole('system-admin')) {
            return $query;
        }

        return $query->whereIn($departmentColumn, $user->accessibleDepartmentIds());
    }
}
