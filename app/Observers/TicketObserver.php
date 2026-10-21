<?php

namespace App\Observers;

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketResolvedNotification;
use App\Services\MailerResolver;
use Illuminate\Support\Facades\Notification;

class TicketObserver
{
    /**
     * Kirim email ke semua user di handler department saat ticket baru dibuat.
     *
     * Setiap user dikirim via SMTP sesuai domain emailnya:
     *
     *   @kpmog.com  → mailer 'kpmog'  → from noreply@kpmog.com
     *
     *   @apca.com   → mailer 'apca'   → from noreply@apca.com
     *   lainnya     → mailer default
     */
    public function created(Ticket $ticket): void
    {
        if (! $ticket->handler_department_id) {
            return;
        }

        $ticket->loadMissing('handlerDepartment');

        if ($ticket->handlerDepartment?->requiresCboApproval()) {
            return;
        }

        $recipients = User::query()
            ->with(['roles.permissions', 'directPermissions'])
            ->whereHas(
                'employee',
                fn ($q) => $q
                    ->where('department_id', $ticket->handler_department_id)
                    ->where('is_active', true)
            )
            ->get()
            ->filter(fn (User $user): bool => $user->hasPermission('tickets.view') || $user->hasPermission('tickets.manage'));

        foreach ($recipients as $user) {
            $mailerName = MailerResolver::resolveMailerName($user->email);
            $from = MailerResolver::fromAddress($mailerName);

            $notification = new TicketCreatedNotification($ticket, $mailerName, $from);
            Notification::sendNow($user, $notification, ['database']);

            if (filled($user->email)) {
                $user->notify($notification);
            }
        }
    }

    /**
     * Kirim email ke requester ketika status ticket berubah menjadi resolved.
     */
    public function updated(Ticket $ticket): void
    {
        if (
            ! $ticket->wasChanged('status')
            || $ticket->status !== 'resolved'
        ) {
            return;
        }

        $requester = $ticket->employee?->user;

        if (! $requester || ! $requester->email) {
            return;
        }

        $mailerName = MailerResolver::resolveMailerName($requester->email);
        $from = MailerResolver::fromAddress($mailerName);

        $requester->notify(
            new TicketResolvedNotification($ticket, $mailerName, $from)
        );
    }
}
