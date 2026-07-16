<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Ticket  $ticket
     * @param  string  $mailerName  Nama mailer: 'kpmog', 'apca', atau 'log'/'smtp'
     * @param  array   $from        ['address' => ..., 'name' => ...]
     */
    public function __construct(
        public readonly Ticket $ticket,
        string $mailerName = 'log',
        public readonly array $from = [],
    ) {
        // Set mailer agar Notification menggunakan SMTP yang tepat
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

        $priority = match ($ticket->priority) {
            'high'   => '🔴 High',
            'medium' => '🟡 Medium',
            'low'    => '🟢 Low',
            default  => $ticket->priority ?? '-',
        };

        return (new MailMessage)
            ->from($fromAddress, $fromName)
            ->subject("[IK WorkDesk] Ticket Baru: {$ticket->ticket_no}")
            ->greeting("Halo, {$notifiable->name}!")
            ->line('Ada **ticket baru** yang masuk ke departemen Anda dan membutuhkan penanganan.')
            ->line('---')
            ->line("**No. Ticket :** {$ticket->ticket_no}")
            ->line("**Subjek     :** {$ticket->subject}")
            ->line("**Prioritas  :** {$priority}")
            ->line("**Dari       :** {$ticket->employee?->name} ({$ticket->requesterDepartment?->name})")
            ->line("**Ke         :** {$ticket->handlerDepartment?->name}")
            ->line("**Dibuat     :** {$ticket->created_at?->format('d M Y H:i')}")
            ->action('Lihat Ticket', $viewUrl)
            ->line('Segera tindak lanjuti ticket ini.')
            ->salutation('IK WorkDesk System');
    }
}
