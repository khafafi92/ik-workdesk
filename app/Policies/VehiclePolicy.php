<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $this->canManage($user)
            && ! $vehicle->bookings()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function restore(User $user, Vehicle $vehicle): bool
    {
        return false;
    }

    public function forceDelete(User $user, Vehicle $vehicle): bool
    {
        return false;
    }

    private function canManage(User $user): bool
    {
        return $user->is_admin === true
            || $user->hasRole('system-admin')
            || $user->hasPermission('vehicles.manage');
    }
}
