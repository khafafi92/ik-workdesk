<?php

namespace App\Policies;

use App\Models\MeetingRoom;
use App\Models\User;

class MeetingRoomPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, MeetingRoom $meetingRoom): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, MeetingRoom $meetingRoom): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, MeetingRoom $meetingRoom): bool
    {
        return $this->canManage($user)
            && ! $meetingRoom->bookings()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function restore(User $user, MeetingRoom $meetingRoom): bool
    {
        return false;
    }

    public function forceDelete(User $user, MeetingRoom $meetingRoom): bool
    {
        return false;
    }

    private function canManage(User $user): bool
    {
        return $user->is_admin === true
            || $user->hasRole('system-admin')
            || $user->hasPermission('meeting-rooms.manage');
    }
}
