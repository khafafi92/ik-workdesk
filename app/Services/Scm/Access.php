<?php

namespace App\Services\Scm;

use App\Models\Scm\Procurement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class Access
{
    public static function has(?User $user, string $permission): bool
    {
        if (! $user || ! $user->isActiveForAccess()) {
            return false;
        }
        $user->loadMissing(['directPermissions', 'roles.permissions']);

        return $user->directPermissions->contains(fn ($p) => $p->is_active && $p->code === $permission)
            || $user->roles->contains(fn ($role) => $role->is_active && $role->permissions->contains(fn ($p) => $p->is_active && $p->code === $permission));
    }

    public static function manages(?User $user, ?Procurement $request = null): bool
    {
        return self::has($user, 'scm.process') && (! $request || ($request->status !== 'draft' && $user->canAccessDepartment($request->department_id)));
    }

    public static function views(User $user, Procurement $request): bool
    {
        return (self::has($user, 'scm.request') && (int) $request->requester_id === (int) $user->id) || self::manages($user, $request);
    }

    public static function scope(Builder $query, User $user): Builder
    {
        return $query->where(function ($query) use ($user): void {
            if (self::has($user, 'scm.request')) {
                $query->where('requester_id', $user->id);
            } else {
                $query->whereRaw('1 = 0');
            }
            if (self::manages($user)) {
                $query->orWhere(fn ($q) => $q->where('status', '!=', 'draft')->whereIn('department_id', $user->accessibleDepartmentIds()));
            }
        });
    }
}
