<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketResolvedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Ticket $ticket,
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
        $ticket      = $this->ticket;
        $viewUrl     = route('filament.admin.resources.service-desk.view', ['record' => $ticket->id]);
        $fromAddress = $this->from['address'] ?? config('mail.from.address');
        $fromName    = $this->from['name']    ?? config('mail.from.name');

        $resolvedAt = $ticket->resolved_at
            ? $ticket->resolved_at->format('d M Y H:i')
            : now()->format('d M Y H:i');

        return (new MailMessage)
            ->from($fromAddress, $fromName)
            ->subject("[IK WorkDesk] ✅ Ticket Selesai: {$ticket->ticket_no}")
            ->greeting("Halo, {$notifiable->name}!")
            ->line('Ticket Anda telah **diselesaikan** oleh tim handler.')
            ->line('---')
            ->line("**No. Ticket     :** {$ticket->ticket_no}")
            ->line("**Subjek         :** {$ticket->subject}")
            ->line("**Diselesaikan   :** {$resolvedAt}")
            ->when(
                filled($ticket->resolution_notes),
                fn (MailMessage $mail) => $mail
                    ->line("**Catatan Resolusi:** {$ticket->resolution_notes}")
            )
            ->action('Lihat Detail Ticket', $viewUrl)
            ->line('Jika masih ada yang perlu ditindaklanjuti, silakan buka ticket baru atau tambahkan komentar.')
            ->salutation('IK WorkDesk System');
    }
}
