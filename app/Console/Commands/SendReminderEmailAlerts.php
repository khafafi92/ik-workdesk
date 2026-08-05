<?php

namespace App\Console\Commands;

use App\Mail\ReminderAlertMail;
use App\Models\Reminder;
use App\Services\MailerResolver;
use Illuminate\Console\Command;
use Throwable;

class SendReminderEmailAlerts extends Command
{
    protected $signature = 'reminders:send-email-alerts';

    protected $description = 'Send due H-3/H-1 email alerts for pending reminders';

    public function handle(): int
    {
        $sent = 0;
        $failed = 0;

        // Proses alarm terdekat lebih dulu. Dengan begitu reminder yang baru
        // dibuat saat sudah masuk H-1 tidak menerima email catch-up H-3 juga.
        foreach ([1, 3] as $daysBefore) {
            Reminder::query()
                ->with(['employee.user'])
                ->where('status', 'pending')
                ->whereDate('reminder_at', '>', now()->toDateString())
                ->whereDate('reminder_at', '<=', now()->addDays($daysBefore)->toDateString())
                ->whereJsonContains('email_alarm_days', $daysBefore)
                ->whereDoesntHave('emailDeliveries', function ($query) use ($daysBefore) {
                    $query->where('days_before', $daysBefore)
                        ->whereNotNull('sent_at');
                })
                ->whereDoesntHave('emailDeliveries', function ($query) use ($daysBefore) {
                    $query->where('days_before', '<', $daysBefore)
                        ->whereNotNull('sent_at');
                })
                ->chunkById(100, function ($reminders) use ($daysBefore, &$sent, &$failed) {
                    foreach ($reminders as $reminder) {
                        $employee = $reminder->employee;
                        $email = $employee?->email ?: $employee?->user?->email;

                        if (! $email) {
                            $this->warn("Reminder #{$reminder->id} dilewati: penerima tidak memiliki email.");

                            continue;
                        }

                        $delivery = $reminder->emailDeliveries()->firstOrCreate([
                            'days_before' => $daysBefore,
                        ]);

                        if ($delivery->sent_at !== null) {
                            continue;
                        }

                        try {
                            $mailerName = MailerResolver::resolveMailerName($email);
                            $from = MailerResolver::fromAddress($mailerName);

                            MailerResolver::forEmail($email)
                                ->to($email, $employee?->name)
                                ->send(
                                    (new ReminderAlertMail(
                                        $reminder,
                                        $daysBefore,
                                        $employee?->name ?? 'User',
                                    ))->from($from['address'], $from['name'])
                                );

                            $delivery->update(['sent_at' => now()]);
                            $reminder->update([
                                'is_notified' => true,
                                'notified_at' => now(),
                            ]);
                            $sent++;
                        } catch (Throwable $exception) {
                            report($exception);
                            $failed++;
                            $this->error("Reminder #{$reminder->id} gagal dikirim: {$exception->getMessage()}");
                        }
                    }
                });
        }

        $this->info("Email reminder terkirim: {$sent}; gagal: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
