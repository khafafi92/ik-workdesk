<?php

namespace App\Notifications;

use App\Models\TicketComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCommentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly TicketComment $comment,
        string $mailerName = 'log',
        public readonly array $from = [],
    ) {
        $this->mailer = $mailerName;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $comment     = $this->comment;
        $ticket      = $comment->ticket;
        $sender      = $comment->user?->name ?? 'System';
        $viewUrl     = route('filament.admin.resources.service-desk.view', ['record' => $ticket->id]);
        $fromAddress = $this->from['address'] ?? config('mail.from.address');
        $fromName    = $this->from['name']    ?? config('mail.from.name');

        $preview = mb_substr(strip_tags($comment->message), 0, 200);
        if (mb_strlen($comment->message) > 200) {
            $preview .= '...';
        }

        $hasAttachments = ! empty($comment->attachments);

        return (new MailMessage)
            ->from($fromAddress, $fromName)
            ->subject("[IK WorkDesk] Update Ticket: {$ticket->ticket_no}")
            ->greeting("Halo, {$notifiable->name}!")
            ->line('Ada **komentar / update baru** pada ticket yang berhubungan dengan Anda.')
            ->line('---')
            ->line("**No. Ticket :** {$ticket->ticket_no}")
            ->line("**Subjek     :** {$ticket->subject}")
            ->line("**Dari       :** {$sender}")
            ->line("**Pesan      :** {$preview}")
            ->when(
                $hasAttachments,
                fn (MailMessage $mail) => $mail->line(
                    '📎 ' . count($comment->attachments) . ' lampiran tersedia.'
                )
            )
            ->action('Lihat Ticket', $viewUrl)
            ->line('Silakan login untuk membalas atau melihat detail selengkapnya.')
            ->salutation('IK WorkDesk System');
    }
}
