<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'is_admin',
])]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withTimestamps();
    }

    public function accessibleDepartments(): BelongsToMany
    {
        return $this->belongsToMany(
            Department::class,
            'department_user_accesses'
        )->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | Role Access
    |--------------------------------------------------------------------------
    */

    public function hasRole(string ...$roleCodes): bool
    {
        if ($this->is_admin === true) {
            return true;
        }

        /*
         * Relasi roles hanya dimuat sekali dalam satu request.
         * Pemanggilan berikutnya memakai data yang sudah ada di memory.
         */
        $this->loadMissing('roles');

        return $this->roles
            ->where('is_active', true)
            ->contains(
                fn (Role $role): bool =>
                    in_array($role->code, $roleCodes, true)
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Permission Access
    |--------------------------------------------------------------------------
    */

    public function hasPermission(string $permissionCode): bool
    {
        if ($this->is_admin === true) {
            return true;
        }

        /*
         * Roles dan permissions dimuat sekali.
         * Filament dapat memanggil method ini berkali-kali tanpa query ulang.
         */
        $this->loadMissing('roles.permissions');

        return $this->roles
            ->where('is_active', true)
            ->contains(
                function (Role $role) use ($permissionCode): bool {
                    return $role->permissions
                        ->where('is_active', true)
                        ->contains(
                            fn (Permission $permission): bool =>
                                $permission->code === $permissionCode
                        );
                }
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Department Access
    |--------------------------------------------------------------------------
    */

    public function belongsToDepartment(?int $departmentId): bool
    {
        if ($departmentId === null) {
            return false;
        }

        $this->loadMissing('employee');

        $userDepartmentId = $this->employee?->department_id;

        return $userDepartmentId !== null
            && (int) $userDepartmentId === (int) $departmentId;
    }

    public function accessibleDepartmentIds(): array
    {
        /*
         * Super Administrator dianggap dapat mengakses semua department.
         */
        if (
            $this->is_admin === true
            || $this->hasRole('system-admin')
        ) {
            return Department::query()
                ->pluck('id')
                ->map(
                    fn ($departmentId): int =>
                        (int) $departmentId
                )
                ->all();
        }

        /*
         * Employee dan department tambahan hanya dimuat sekali.
         */
        $this->loadMissing([
            'employee',
            'accessibleDepartments',
        ]);

        $departmentIds = $this->accessibleDepartments
            ->pluck('id')
            ->map(
                fn ($departmentId): int =>
                    (int) $departmentId
            )
            ->all();

        $homeDepartmentId = $this->employee?->department_id;

        /*
         * Department asli employee otomatis selalu dapat diakses.
         */
        if ($homeDepartmentId !== null) {
            $departmentIds[] = (int) $homeDepartmentId;
        }

        return array_values(
            array_unique($departmentIds)
        );
    }

    public function canAccessDepartment(?int $departmentId): bool
    {
        if ($departmentId === null) {
            return false;
        }

        if (
            $this->is_admin === true
            || $this->hasRole('system-admin')
        ) {
            return true;
        }

        return in_array(
            (int) $departmentId,
            $this->accessibleDepartmentIds(),
            true
        );
    }
}
