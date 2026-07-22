<?php

namespace App\Notifications;

use App\Models\TicketComment;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCommentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $mailer;

    public function __construct(
        public readonly TicketComment $comment,
        string $mailerName = 'log',
        public readonly array $from = [],
    ) {
        $this->mailer = $mailerName;
    }

    public function via(object $notifiable): array
    {
        return (
            $this->comment->activity_type === 'message'
            && filled($notifiable->routeNotificationFor('mail'))
        ) ? ['mail'] : [];
    }

    public function toDatabase(object $notifiable): array
    {
        $comment = $this->comment->loadMissing([
            'ticket',
            'user',
            'workTask',
            'department',
        ]);
        $ticket = $comment->ticket;
        $sender = $comment->user?->name ?? 'System';
        $isMessage = $comment->activity_type === 'message';

        if ($isMessage) {
            $url = route(
                'filament.admin.resources.service-desk.view',
                ['record' => $ticket->id]
            ) . "#activity-{$comment->id}";
            $title = "Balasan Baru: {$ticket->ticket_no}";
            $body = "{$sender}: " . mb_strimwidth(
                strip_tags($comment->message),
                0,
                160,
                '...'
            );
            $icon = 'heroicon-o-chat-bubble-left-right';
        } else {
            $task = $comment->workTask;
            $url = route(
                'filament.admin.resources.work-tasks.view',
                ['record' => $task->id]
            );
            $title = match ($comment->activity_type) {
                'status_change' => "Status Task Diperbarui: {$task->task_no}",
                'progress_change' => "Progres Task Diperbarui: {$task->task_no}",
                'pic_change' => "PIC Task Diperbarui: {$task->task_no}",
                'due_date_change' => "Deadline Task Diperbarui: {$task->task_no}",
                'notes_change' => "Catatan Task Diperbarui: {$task->task_no}",
                default => "Aktivitas Task: {$task->task_no}",
            };
            $body = $comment->message;
            $icon = 'heroicon-o-arrow-path';
        }

        return FilamentNotification::make()
            ->title($title)
            ->body($body)
            ->icon($icon)
            ->info()
            ->actions([
                Action::make('open')
                    ->label($isMessage ? 'Buka Balasan' : 'Buka Work Log')
                    ->button()
                    ->url($url)
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
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
