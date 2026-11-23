<?php

namespace App\Notifications;

use App\Models\WorkTask;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LegalTaskApprovalRequestedNotification extends Notification implements ShouldQueue
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
            ->title("Approval Work Log: {$task->task_no}")
            ->body(
                ($task->title ?: 'New Legal Log Work')
                .($task->ticket
                    ? " · Parent {$task->ticket->ticket_no}"
                    : '')
                .' waiting for CBO\'s decision.'
            )
            ->icon('heroicon-o-shield-check')
            ->warning()
            ->actions([
                Action::make('open')
                    ->label('Review Work Log')
                    ->button()
                    ->url($url)
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $task = $this->workTask->loadMissing(['ticket', 'department']);
        $viewUrl = route(
            'filament.admin.resources.work-tasks.view',
            ['record' => $task->id]
        );
        $fromAddress = $this->from['address'] ?? config('mail.from.address');
        $fromName = $this->from['name'] ?? config('mail.from.name');

        return (new MailMessage)
            ->from($fromAddress, $fromName)
            ->subject("[IK WorkDesk] Approval Work Log: {$task->task_no}")
            ->greeting("Halo, {$notifiable->name}!")
            ->line('There\'s a new Legal Log Work that needs your approval.')
            ->line('---')
            ->line("**No. Task  :** {$task->task_no}")
            ->line("**Title:* {$task->title}")
            ->line("**Departement:* {$task->department?->name}")
            ->when(
                $task->ticket,
                fn (MailMessage $mail) => $mail->line(
                    "**From Ticket:* {$task->ticket->ticket_no} — {$task->ticket->subject}"
                )
            )
            ->action('Review Work Log', $viewUrl)
            ->line('Please approve or reject Work Log according to the review.')
            ->salutation('IK WorkDesk System');
    }
}
