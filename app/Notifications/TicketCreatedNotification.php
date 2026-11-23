<?php

namespace App\Notifications;

use App\Models\Ticket;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $mailerName  Nama mailer: 'kpmog', 'apca', atau 'log'/'smtp'
     * @param  array  $from  ['address' => ..., 'name' => ...]
     */
    public function __construct(
        public readonly Ticket $ticket,
        public readonly string $mailerName = 'log',
        public readonly array $from = [],
    ) {
        $this->afterCommit();
        $this->onQueue('service-desk-notifications');
    }

    public function via(object $notifiable): array
    {
        return filled($notifiable->routeNotificationFor('mail')) ? ['mail'] : [];
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title("New request: {$this->ticket->ticket_no}")
            ->body(e($this->ticket->subject))
            ->icon('heroicon-o-inbox')->info()
            ->actions([
                Action::make('open')->label('Open request')->button()
                    ->url(route('filament.admin.resources.service-desk.view', ['record' => $this->ticket->id]))
                    ->markAsRead(),
            ])->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticket = $this->ticket;
        $viewUrl = route('filament.admin.resources.service-desk.view', ['record' => $ticket->id]);
        $fromAddress = $this->from['address'] ?? config('mail.from.address');
        $fromName = $this->from['name'] ?? config('mail.from.name');

        $priority = match ($ticket->priority) {
            'high' => '🔴 High',
            'medium' => '🟡 Medium',
            'low' => '🟢 Low',
            default => $ticket->priority ?? '-',
        };

        return (new MailMessage)
            ->mailer($this->mailerName)
            ->from($fromAddress, $fromName)
            ->subject("[IK WorkDesk] New Ticket: {$ticket->ticket_no}")
            ->greeting("Halo, {$notifiable->name}!")
            ->line('There\'s a new **ticket that goes into your department and needs handling.')
            ->line('---')
            ->line("**No. Ticket :** {$ticket->ticket_no}")
            ->line("**Subject:** {$ticket->subject}")
            ->line("**Priority:** {$priority}")
            ->line("**From the ** {$ticket->employee?->name} ({$ticket->requesterDepartment?->name})")
            ->line("**To:** {$ticket->handlerDepartment?->name}")
            ->line("**Made:* {$ticket->created_at?->format('d M Y H:i')}")
            ->action('View Ticket', $viewUrl)
            ->line('Please follow up on this ticket.')
            ->salutation('IK WorkDesk System');
    }
}
