<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RoleAssignmentService
{
    public function constrainAssignableRoles(Builder $query, ?User $actor): Builder
    {
        $query->where('is_active', true);

        if ($actor?->is_admin === true) {
            return $query;
        }

        $permissionIds = $this->actorPermissionIds($actor);

        return $query
            ->where('code', '!=', 'system-admin')
            ->whereDoesntHave(
                'permissions',
                fn (Builder $permissionQuery): Builder => $permissionQuery
                    ->where('permissions.is_active', true)
                    ->when(
                        $permissionIds->isNotEmpty(),
                        fn (Builder $builder): Builder => $builder->whereNotIn(
                            'permissions.id',
                            $permissionIds->all()
                        )
                    )
            );
    }

    public function filterAssignableRoleIds(?User $actor, array $roleIds): array
    {
        $requestedIds = collect($roleIds)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($requestedIds->isEmpty()) {
            return [];
        }

        return $this->constrainAssignableRoles(Role::query(), $actor)
            ->whereIn('id', $requestedIds->all())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function actorPermissionIds(?User $actor): Collection
    {
        if (! $actor) {
            return collect();
        }

        return $actor->roles()
            ->where('roles.is_active', true)
            ->with([
                'permissions' => fn ($query) => $query->where('permissions.is_active', true),
            ])
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('id')
            ->unique()
            ->values();
    }
}
