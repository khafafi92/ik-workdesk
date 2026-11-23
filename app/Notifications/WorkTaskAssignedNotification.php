<?php

namespace App\Notifications;

use App\Models\WorkTask;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkTaskAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $mailer;

    public function __construct(
        public readonly WorkTask $workTask,
        string $mailerName = 'log',
        public readonly array $from = [],
    ) {
        $this->mailer = $mailerName;
    }

    public function via(object $notifiable): array
    {
        return filled($notifiable->routeNotificationFor('mail'))
            ? ['mail']
            : [];
    }

    public function toDatabase(object $notifiable): array
    {
        $task = $this->workTask->loadMissing(['ticket', 'department']);
        $url = route(
            'filament.admin.resources.work-tasks.view',
            ['record' => $task->id]
        );

        return FilamentNotification::make()
            ->title("New Log Work: {$task->task_no}")
            ->body(
                ($task->title ?: 'New Log Work')
                .($task->ticket
                    ? " · Parent {$task->ticket->ticket_no}"
                    : '')
            )
            ->icon('heroicon-o-clipboard-document-check')
            ->info()
            ->actions([
                Action::make('open')
                    ->label('Open Work Log')
                    ->button()
                    ->url($url)
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $task = $this->workTask;
        $ticket = $task->ticket;
        $viewUrl = route('filament.admin.resources.work-tasks.view', ['record' => $task->id]);
        $fromAddress = $this->from['address'] ?? config('mail.from.address');
        $fromName = $this->from['name'] ?? config('mail.from.name');

        $priority = match ($task->priority) {
            'high' => '🔴 High',
            'medium' => '🟡 Medium',
            'low' => '🟢 Low',
            default => $task->priority ?? '-',
        };

        $due = $task->due_at
            ? $task->due_at->format('d M Y H:i')
            : 'Not specified';

        return (new MailMessage)
            ->from($fromAddress, $fromName)
            ->subject("[IK WorkDesk] New Log Work: {$task->task_no}")
            ->greeting("Halo, {$notifiable->name}!")
            ->line('There\'s a new **work log* that\'s assigned to your department.')
            ->line('---')
            ->line("**No. Task    :** {$task->task_no}")
            ->line("**Title:* {$task->title}")
            ->line("**Priority:** {$priority}")
            ->line("**Deadline    :** {$due}")
            ->when(
                $ticket,
                fn (MailMessage $mail) => $mail
                    ->line("**From Ticket:* {$ticket->ticket_no} — {$ticket->subject}")
            )
            ->action('View Work Log', $viewUrl)
            ->line('Immediately process this work log to the priority.')
            ->salutation('IK WorkDesk System');
    }
}
