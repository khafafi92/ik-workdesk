<?php

namespace App\Observers;

use App\Models\TicketComment;
use App\Models\User;
use App\Notifications\TicketCommentNotification;
use App\Services\MailerResolver;

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
        $ticket = $comment->ticket;

        if (! $ticket) {
            return;
        }

        $senderUserId = $comment->user_id;

        // Kumpulkan semua department ID yang terlibat
        $departmentIds = collect();

        if ($ticket->handler_department_id) {
            $departmentIds->push($ticket->handler_department_id);
        }

        if ($ticket->requester_department_id) {
            $departmentIds->push($ticket->requester_department_id);
        }

        // Untuk collaborative: tambahkan semua department yang ada di assignments
        if ($ticket->workflow_type === 'collaborative') {
            $assignedDeptIds = $ticket->assignments()->pluck('department_id');
            $departmentIds   = $departmentIds->merge($assignedDeptIds);
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
            ->whereNotNull('email')
            ->when(
                $senderUserId,
                fn ($q) => $q->where('id', '!=', $senderUserId)
            )
            ->get();

        // Tambahkan requester jika belum ada dan bukan si pengirim komentar
        $requester = $ticket->employee?->user;

        if (
            $requester
            && $requester->email
            && (int) $requester->id !== (int) $senderUserId
            && ! $recipients->contains('id', $requester->id)
        ) {
            $recipients->push($requester);
        }

        foreach ($recipients as $user) {
            $mailerName = MailerResolver::resolveMailerName($user->email);
            $from       = MailerResolver::fromAddress($mailerName);

            $user->notify(
                new TicketCommentNotification($comment, $mailerName, $from)
            );
        }
    }
}
