<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdministrator();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isSystemAdministrator();
    }

    public function create(User $user): bool
    {
        return $user->isSystemAdministrator();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isSystemAdministrator();
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isSystemAdministrator()
            && (int) $user->id !== (int) $model->id;
    }

    public function restore(User $user, User $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
