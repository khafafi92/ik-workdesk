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
                ($task->title ?: 'Work Log Legal baru')
                .($task->ticket
                    ? " · Induk {$task->ticket->ticket_no}"
                    : '')
                .' menunggu keputusan CBO.'
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
            ->line('Ada Work Log Legal baru yang membutuhkan approval Anda.')
            ->line('---')
            ->line("**No. Task  :** {$task->task_no}")
            ->line("**Judul     :** {$task->title}")
            ->line("**Departemen:** {$task->department?->name}")
            ->when(
                $task->ticket,
                fn (MailMessage $mail) => $mail->line(
                    "**Dari Ticket:** {$task->ticket->ticket_no} — {$task->ticket->subject}"
                )
            )
            ->action('Review Work Log', $viewUrl)
            ->line('Silakan approve atau reject Work Log tersebut sesuai hasil review.')
            ->salutation('IK WorkDesk System');
    }
}
