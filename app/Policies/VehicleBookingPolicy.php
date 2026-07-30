<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VehicleBooking;

class VehicleBookingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('vehicle-bookings.view')
            || $this->manageAny($user);
    }

    public function view(
        User $user,
        VehicleBooking $vehicleBooking
    ): bool {
        return $this->manageAny($user)
            || (
                $user->hasPermission('vehicle-bookings.view')
                && (int) $vehicleBooking->requester_id
                    === (int) $user->id
            );
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('vehicle-bookings.create')
            || $this->manageAny($user);
    }

    public function update(
        User $user,
        VehicleBooking $vehicleBooking
    ): bool {
        if (
            $vehicleBooking->status !== 'confirmed'
            || $vehicleBooking->end_at?->isPast() === true
        ) {
            return false;
        }

        return $this->manageAny($user)
            || (
                $user->hasPermission('vehicle-bookings.create')
                && (int) $vehicleBooking->requester_id
                    === (int) $user->id
            );
    }

    public function delete(
        User $user,
        VehicleBooking $vehicleBooking
    ): bool {
        return $this->manageAny($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->manageAny($user);
    }

    public function cancel(
        User $user,
        VehicleBooking $vehicleBooking
    ): bool {
        return $vehicleBooking->canBeCancelled()
            && (
                $this->manageAny($user)
                || (
                    $user->hasPermission(
                        'vehicle-bookings.cancel-own'
                    )
                    && (int) $vehicleBooking->requester_id
                        === (int) $user->id
                )
            );
    }

    public function complete(
        User $user,
        VehicleBooking $vehicleBooking
    ): bool {
        return $vehicleBooking->canBeCompleted()
            && (
                $this->manageAny($user)
                || (
                    $user->hasPermission(
                        'vehicle-bookings.create'
                    )
                    && (int) $vehicleBooking->requester_id
                        === (int) $user->id
                )
            );
    }

    public function manageAny(User $user): bool
    {
        return $user->is_admin === true
            || $user->hasRole('system-admin')
            || $user->hasPermission('vehicle-bookings.manage');
    }

    public function restore(
        User $user,
        VehicleBooking $vehicleBooking
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        VehicleBooking $vehicleBooking
    ): bool {
        return false;
    }
}
