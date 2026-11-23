<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

class UserAccessHierarchyService
{
    public const REQUESTER = 'requester';

    private const LEVELS = [
        'system-admin' => 'Sys Administrator JUNE whole system access',
        'administrator' => 'Administrator ▪ manage user and access setup',
        'admin' => 'Admin ▪ operates according to assigned menu',
        'department-manager' => 'Managers of the department limit and work process',
        'supervisor' => 'SPV\'s department limits and work supervisors',
        'general-affairs' => 'General Affairs - Operations',
        'attendance-operator' => 'Attendance Operator — proses attendance',
        'management' => 'Management (CBO, COO I, II, III)',
        'requester' => 'Requester\'s own request process',
    ];

    public function optionsFor(?User $actor): array
    {
        $options = self::LEVELS;

        if ($actor?->isSystemAdministrator()) {
            return $options;
        }

        if ($actor?->hasRole('administrator')) {
            unset($options['system-admin']);

            return $options;
        }

        return [];
    }

    public function canAssign(?User $actor, string $level): bool
    {
        return array_key_exists($level, $this->optionsFor($actor));
    }

    public function levelFor(User $user): string
    {
        if ($user->isSystemAdministrator()) {
            return 'system-admin';
        }
        if ($user->access_level === 'cbo') {
            return 'management';
        }

        if (array_key_exists((string) $user->access_level, self::LEVELS)) {
            return $user->access_level;
        }

        if ($user->is_admin === true) {
            return 'system-admin';
        }

        $roleCodes = $user->roles()
            ->whereIn('code', [...array_keys(self::LEVELS), 'cbo'])
            ->pluck('code')
            ->all();

        foreach (array_keys(self::LEVELS) as $level) {
            if (in_array($level, $roleCodes, true) || ($level === 'management' && in_array('cbo', $roleCodes, true))) {
                return $level;
            }
        }

        return self::REQUESTER;
    }

    public function syncPrimaryRole(User $user, string $level): void
    {
        $roleId = Role::query()
            ->where('code', $level)
            ->where('is_active', true)
            ->value('id');

        abort_unless($roleId, 422, 'Access level not available.');

        $primaryRoleIds = Role::query()
            ->whereIn('code', [...array_keys(self::LEVELS), 'cbo'])
            ->pluck('id');

        $user->roles()->detach($primaryRoleIds);
        $user->roles()->attach($roleId);
        $user->forceFill(['access_level' => $level])->saveQuietly();
        $user->unsetRelation('roles');
    }

    public function hierarchyRoleCodes(): Collection
    {
        return collect(array_keys(self::LEVELS));
    }
}
