<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;

class TicketCommentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasTicketPermission($user);
    }

    public function view(
        User $user,
        TicketComment $ticketComment
    ): bool {
        return $this->canAccessTicket(
            $user,
            $ticketComment->ticket
        );
    }

    public function create(User $user): bool
    {
        return $this->hasTicketPermission($user);
    }

    public function update(
        User $user,
        TicketComment $ticketComment
    ): bool {
        return (int) $ticketComment->user_id === (int) $user->id
            && $this->canAccessTicket($user, $ticketComment->ticket);
    }

    public function delete(
        User $user,
        TicketComment $ticketComment
    ): bool {
        return (int) $ticketComment->user_id === (int) $user->id
            && $this->canAccessTicket($user, $ticketComment->ticket);
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

    private function hasTicketPermission(User $user): bool
    {
        return $user->hasPermission('tickets.view')
            || $user->hasPermission('tickets.manage');
    }

    private function canAccessTicket(
        User $user,
        ?Ticket $ticket
    ): bool {
        if (! $ticket || ! $this->hasTicketPermission($user)) {
            return false;
        }

        if ($user->is_admin || $user->hasRole('system-admin')) {
            return true;
        }

        if (
            $user->employee
            && (int) $ticket->employee_id === (int) $user->employee->id
        ) {
            return true;
        }

        $departmentIds = $user->accessibleDepartmentIds();

        return in_array(
            (int) $ticket->requester_department_id,
            $departmentIds,
            true
        )
            || in_array(
                (int) $ticket->handler_department_id,
                $departmentIds,
                true
            )
            || $ticket->assignments()
                ->whereIn('department_id', $departmentIds)
                ->exists();
    }
}
