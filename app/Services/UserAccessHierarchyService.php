<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

class UserAccessHierarchyService
{
    public const REQUESTER = 'requester';

    private const LEVELS = [
        'system-admin' => 'Sys Administrator — seluruh akses sistem',
        'administrator' => 'Administrator — kelola user dan penetapan akses',
        'admin' => 'Admin — operasional sesuai menu yang ditugaskan',
        'department-manager' => 'Manager — kelola pekerjaan dan monitoring department',
        'supervisor' => 'SPV — supervisi pekerjaan team',
        'general-affairs' => 'General Affairs — pengelolaan ATK',
        'attendance-operator' => 'Attendance Operator — pengelolaan attendance',
        'cbo' => 'Chief Business Officer — persetujuan Legal',
        'requester' => 'Requester — Service Desk milik sendiri',
    ];

    public function optionsFor(?User $actor): array
    {
        $options = self::LEVELS;

        if ($actor?->is_admin === true) {
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
        if (array_key_exists((string) $user->access_level, self::LEVELS)) {
            return $user->access_level;
        }

        if ($user->is_admin === true) {
            return 'system-admin';
        }

        $roleCodes = $user->roles()
            ->whereIn('code', array_keys(self::LEVELS))
            ->pluck('code')
            ->all();

        foreach (array_keys(self::LEVELS) as $level) {
            if (in_array($level, $roleCodes, true)) {
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

        abort_unless($roleId, 422, 'Level akses tidak tersedia.');

        $primaryRoleIds = Role::query()
            ->whereIn('code', array_keys(self::LEVELS))
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
