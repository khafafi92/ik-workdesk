<?php

namespace App\Services\Inventory;

use App\Enums\ItPermission;
use App\Models\InventoryBalance;
use App\Models\InventoryWarehouse;
use App\Models\User;
use App\Services\ItInfrastructure\ItInfrastructureAccess;
use App\Services\Scm\Access;
use Illuminate\Database\Eloquent\Builder;

class InventoryAccess
{
    public static function administer(?User $user): bool
    {
        return Access::has($user, 'inventory.manage');
    }

    public static function viewAll(?User $user): bool
    {
        return self::administer($user) || Access::has($user, 'inventory.view-all');
    }

    public static function viewCatalogue(?User $user): bool
    {
        return self::viewAll($user) || Access::has($user, 'inventory.department-issue') || Access::has($user, 'scm.request') || Access::manages($user)
            || ($user?->isActiveForAccess() && ($user->hasPermission('atk.view') || $user->hasPermission('atk.request') || $user->hasPermission('atk.manage')))
            || ItInfrastructureAccess::hasAny($user, ItPermission::DevicesView, ItPermission::DevicesManage);
    }

    public static function issueDepartment(?User $user, InventoryBalance $balance): bool
    {
        return Access::has($user, 'inventory.department-issue') && $balance->department_id
            && (int) $balance->warehouse->department_id === (int) $balance->department_id
            && $user->canAccessDepartment($balance->department_id);
    }

    public static function editItem(?User $user, string $kind): bool
    {
        return self::administer($user) || match ($kind) {
            'atk' => $user?->isActiveForAccess() && $user->hasPermission('atk.manage'),
            'device' => ItInfrastructureAccess::hasAny($user, ItPermission::DevicesManage),
            'material' => Access::manages($user),
            default => false,
        };
    }

    public static function scopeBalances(Builder $query, User $user): Builder
    {
        if (self::viewAll($user)) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user): void {
            $scope->whereRaw('1 = 0');
            if (Access::has($user, 'inventory.department-issue')) {
                $scope->orWhere(fn ($q) => $q->whereIn('department_id', $user->accessibleDepartmentIds())
                    ->whereHas('warehouse', fn ($w) => $w->whereColumn('inventory_warehouses.department_id', 'inventory_balances.department_id')));
            }
            if ($user->isActiveForAccess() && $user->hasPermission('atk.view')) {
                $scope->orWhere(fn ($q) => $q->whereHas('item', fn ($i) => $i->where('kind', 'atk'))
                    ->whereHas('warehouse', fn ($w) => $w->where('legacy_atk', true)));
            }
            if ($user->isActiveForAccess() && $user->hasPermission('atk.manage')) {
                $scope->orWhereHas('item', fn ($i) => $i->where('kind', 'atk'));
            }
            if (ItInfrastructureAccess::hasAny($user, ItPermission::DevicesManage)) {
                $scope->orWhere(fn ($q) => $q->whereIn('department_id', $user->accessibleDepartmentIds())->whereHas('item', fn ($i) => $i->where('kind', 'device')));
            }
            foreach (['HO', 'PROJECT'] as $kind) {
                if (Access::canProcessNeedType($user, $kind)) {
                    $scope->orWhere(fn ($q) => $q->whereIn('department_id', $user->accessibleDepartmentIds())
                        ->whereHas('warehouse', function ($warehouse) use ($user, $kind): void {
                            $warehouse->where('kind', $kind)->where('legacy_atk', false);
                            if ($kind === 'PROJECT' && ! Access::has($user, 'scm.process')) {
                                $companies = collect(['KPMOG', 'APCA'])->filter(fn ($company) => Access::has($user, 'scm.process-'.strtolower($company)));
                                $warehouse->whereIn('company', $companies);
                            }
                        }));
                }
            }
        });
    }

    public static function warehouseMatches(InventoryWarehouse $warehouse, string $company, string $kind, ?int $projectId): bool
    {
        return $warehouse->is_active && ! $warehouse->legacy_atk && ! $warehouse->department_id && $warehouse->company === $company && $warehouse->kind === $kind
            && ($kind === 'HO' || ((int) $warehouse->project_numbering_id === (int) $projectId && $warehouse->project?->is_active));
    }
}
