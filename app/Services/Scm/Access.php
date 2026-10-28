<?php

namespace App\Services\Scm;

use App\Models\Scm\ApprovalPolicy;
use App\Models\Scm\ApprovalRoute;
use App\Models\Scm\CbeApproval;
use App\Models\Scm\CbeApprovalPolicy;
use App\Models\Scm\Procurement;
use App\Models\Scm\RequestApproval;
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
        return self::has($user, 'scm.process') && (! $request || (! in_array($request->status, ['draft', 'approval']) && $user->canAccessDepartment($request->department_id)));
    }

    public static function approves(?User $user): bool
    {
        return $user && $user->isActiveForAccess() && (
            ApprovalRoute::where('acknowledger_id', $user->id)->orWhere('cbo_id', $user->id)->orWhere('cbo3_id', $user->id)->exists()
            || ApprovalPolicy::where('supervisor_id', $user->id)->orWhere('acknowledger_id', $user->id)->orWhere('crosschecker_id', $user->id)
                ->orWhere('approver_id', $user->id)->orWhere('high_approver_id', $user->id)->exists()
            || RequestApproval::where('user_id', $user->id)->where('role', '!=', 'requested')->exists()
            || CbeApproval::where('user_id', $user->id)->where('role', '!=', 'prepared')->exists()
            || CbeApprovalPolicy::where('acknowledger_id', $user->id)->orWhere('crosschecker_id', $user->id)->orWhere('approver_id', $user->id)->exists()
        );
    }

    public static function signs(?User $user): bool
    {
        return self::has($user, 'scm.request') || self::has($user, 'scm.process') || self::approves($user);
    }

    public static function viewsRequest(User $user, Procurement $request): bool
    {
        return self::views($user, $request) || ($user->isActiveForAccess()
            && $request->approvals()->where('user_id', $user->id)->where('role', '!=', 'requested')->exists());
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
                $query->orWhere(fn ($q) => $q->whereNotIn('status', ['draft', 'approval'])->whereIn('department_id', $user->accessibleDepartmentIds()));
            }
        });
    }
}
