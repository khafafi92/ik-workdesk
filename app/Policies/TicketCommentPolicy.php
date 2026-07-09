<?php

namespace App\Policies;

use App\Models\TicketComment;
use App\Models\User;

class TicketCommentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(
        User $user,
        TicketComment $ticketComment
    ): bool {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(
        User $user,
        TicketComment $ticketComment
    ): bool {
        return (int) $ticketComment->user_id === (int) $user->id;
    }

    public function delete(
        User $user,
        TicketComment $ticketComment
    ): bool {
        return (int) $ticketComment->user_id === (int) $user->id;
    }

    public function restore(
        User $user,
        TicketComment $ticketComment
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        TicketComment $ticketComment
    ): bool {
        return false;
    }
}