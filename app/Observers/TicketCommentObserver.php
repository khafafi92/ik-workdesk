<?php

namespace App\Observers;

use App\Models\TicketComment;
use App\Models\User;
use App\Notifications\TicketCommentNotification;
use App\Services\MailerResolver;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class TicketCommentObserver
{
    /**
     * Kirim notifikasi ke semua pihak terkait ketika ada komentar baru.
     *
     * Penerima:
     * - Requester (pemilik ticket)
     * - Semua user di handler department
     * - Semua user di department yang ter-assign (collaborative)
     *
     * Pengecualian: user yang membuat komentar tidak perlu notif ke dirinya sendiri.
     * Setiap user dikirim via SMTP sesuai domain emailnya.
     */
    public function created(TicketComment $comment): void
    {
        if ($comment->activity_type === 'work_log_created') {
            return;
        }

        $ticket = $comment->ticket;

        if (! $ticket) {
            return;
        }

        $senderUserId = $comment->user_id;

        $isMessage = $comment->activity_type === 'message';

        // Pesan grup dikirim ke seluruh department yang terlibat.
        // Aktivitas operasional hanya dikirim ke department Work Log terkait.
        $departmentIds = collect();

        if ($isMessage) {
            if ($ticket->handler_department_id) {
                $departmentIds->push($ticket->handler_department_id);
            }

            if ($ticket->requester_department_id) {
                $departmentIds->push($ticket->requester_department_id);
            }

            if ($ticket->workflow_type === 'collaborative') {
                $departmentIds = $departmentIds->merge(
                    $ticket->assignments()->pluck('department_id')
                );
            }
        } elseif ($comment->department_id) {
            $departmentIds->push($comment->department_id);
        }

        $departmentIds = $departmentIds->unique()->filter()->values();

        // Ambil semua user di department terkait, kecuali pengirim komentar
        $recipients = User::query()
            ->whereHas(
                'employee',
                fn ($q) => $q
                    ->whereIn('department_id', $departmentIds)
                    ->where('is_active', true)
            )
            ->when(
                $senderUserId,
                fn ($q) => $q->where('id', '!=', $senderUserId)
            )
            ->get()
            ->filter(function (User $user) use ($isMessage): bool {
                if ($isMessage) {
                    return $user->hasPermission('tickets.view')
                        || $user->hasPermission('tickets.manage');
                }

                return $user->hasPermission('worklogs.view')
                    || $user->hasPermission('worklogs.manage');
            });

        // Tambahkan requester jika belum ada dan bukan si pengirim komentar
        $requester = $ticket->employee?->user;

        if (
            $requester
            && (int) $requester->id !== (int) $senderUserId
            && ! $recipients->contains('id', $requester->id)
            && (
                $isMessage
                    ? (
                        $requester->hasPermission('tickets.view')
                        || $requester->hasPermission('tickets.manage')
                    )
                    : (
                        $requester->hasPermission('worklogs.view')
                        || $requester->hasPermission('worklogs.manage')
                    )
            )
        ) {
            $recipients->push($requester);
        }

        foreach ($recipients as $user) {
            $mailerName = MailerResolver::resolveMailerName($user->email);
            $from       = MailerResolver::fromAddress($mailerName);
            $notification = new TicketCommentNotification(
                $comment,
                $mailerName,
                $from
            );

            NotificationFacade::sendNow(
                $user,
                $notification,
                ['database']
            );

            if ($isMessage && filled($user->email)) {
                $user->notify($notification);
            }
        }
    }
}
