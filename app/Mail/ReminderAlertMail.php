<?php

namespace App\Mail;

use App\Filament\Resources\Reminders\ReminderResource;
use App\Models\Reminder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReminderAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Reminder $reminder,
        public readonly int $daysBefore,
        public readonly string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('[IK WorkDesk] Reminder H-%d: %s', $this->daysBefore, $this->reminder->title),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.reminders.alert',
            with: [
                'viewUrl' => ReminderResource::getUrl('view', ['record' => $this->reminder]),
            ],
        );
    }
}
