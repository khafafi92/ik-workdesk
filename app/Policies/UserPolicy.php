<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin === true;
    }

    public function view(User $user, User $model): bool
    {
        return $user->is_admin === true;
    }

    public function create(User $user): bool
    {
        return $user->is_admin === true;
    }

    public function update(User $user, User $model): bool
    {
        return $user->is_admin === true;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->is_admin === true
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