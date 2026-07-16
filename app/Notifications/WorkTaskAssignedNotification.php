<?php

namespace App\Notifications;

use App\Models\WorkTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkTaskAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly WorkTask $workTask,
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
        $task        = $this->workTask;
        $ticket      = $task->ticket;
        $viewUrl     = route('filament.admin.resources.work-tasks.view', ['record' => $task->id]);
        $fromAddress = $this->from['address'] ?? config('mail.from.address');
        $fromName    = $this->from['name']    ?? config('mail.from.name');

        $priority = match ($task->priority) {
            'high'   => '🔴 High',
            'medium' => '🟡 Medium',
            'low'    => '🟢 Low',
            default  => $task->priority ?? '-',
        };

        $due = $task->due_at
            ? $task->due_at->format('d M Y H:i')
            : 'Tidak ditentukan';

        return (new MailMessage)
            ->from($fromAddress, $fromName)
            ->subject("[IK WorkDesk] Work Log Baru: {$task->task_no}")
            ->greeting("Halo, {$notifiable->name}!")
            ->line('Ada **work log baru** yang di-assign ke departemen Anda.')
            ->line('---')
            ->line("**No. Task    :** {$task->task_no}")
            ->line("**Judul       :** {$task->title}")
            ->line("**Prioritas   :** {$priority}")
            ->line("**Deadline    :** {$due}")
            ->when(
                $ticket,
                fn (MailMessage $mail) => $mail
                    ->line("**Dari Ticket :** {$ticket->ticket_no} — {$ticket->subject}")
            )
            ->action('Lihat Work Log', $viewUrl)
            ->line('Segera proses work log ini sesuai prioritas.')
            ->salutation('IK WorkDesk System');
    }
}
