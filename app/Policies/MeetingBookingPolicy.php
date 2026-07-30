<?php

namespace App\Policies;

use App\Models\MeetingBooking;
use App\Models\User;

class MeetingBookingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('meeting-bookings.view')
            || $this->manageAny($user);
    }

    public function view(
        User $user,
        MeetingBooking $meetingBooking
    ): bool {
        if ($this->manageAny($user)) {
            return true;
        }

        if (! $user->hasPermission('meeting-bookings.view')) {
            return false;
        }

        return (int) $meetingBooking->organizer_id === (int) $user->id
            || $meetingBooking->participants()
                ->whereKey($user->id)
                ->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('meeting-bookings.create')
            || $this->manageAny($user);
    }

    public function update(
        User $user,
        MeetingBooking $meetingBooking
    ): bool {
        if (
            $meetingBooking->status !== 'confirmed'
            || $meetingBooking->end_at?->isPast() === true
        ) {
            return false;
        }

        return $this->manageAny($user)
            || (
                $user->hasPermission('meeting-bookings.create')
                && (int) $meetingBooking->organizer_id
                    === (int) $user->id
            );
    }

    public function delete(
        User $user,
        MeetingBooking $meetingBooking
    ): bool {
        return $this->manageAny($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->manageAny($user);
    }

    public function cancel(
        User $user,
        MeetingBooking $meetingBooking
    ): bool {
        return $meetingBooking->canBeCancelled()
            && (
                $this->manageAny($user)
                || (
                    $user->hasPermission(
                        'meeting-bookings.cancel-own'
                    )
                    && (int) $meetingBooking->organizer_id
                        === (int) $user->id
                )
            );
    }

    public function complete(
        User $user,
        MeetingBooking $meetingBooking
    ): bool {
        return $meetingBooking->canBeCompleted()
            && (
                $this->manageAny($user)
                || (
                    $user->hasPermission(
                        'meeting-bookings.create'
                    )
                    && (int) $meetingBooking->organizer_id
                        === (int) $user->id
                )
            );
    }

    public function manageAny(User $user): bool
    {
        return $user->is_admin === true
            || $user->hasRole('system-admin')
            || $user->hasPermission('meeting-bookings.manage');
    }

    public function restore(
        User $user,
        MeetingBooking $meetingBooking
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        MeetingBooking $meetingBooking
    ): bool {
        return false;
    }
}
