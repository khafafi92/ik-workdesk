<?php

namespace App\Observers;

use App\Models\User;
use App\Models\WorkTask;
use App\Notifications\WorkTaskAssignedNotification;
use App\Services\MailerResolver;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class WorkTaskObserver
{
    /**
     * Kirim notifikasi ke semua user di department target
     * ketika WorkTask baru dibuat.
     *
     * Setiap user dikirim via SMTP sesuai domain emailnya.
     */
    public function created(WorkTask $workTask): void
    {
        if (! $workTask->department_id) {
            return;
        }

        $recipients = User::query()
            ->whereHas(
                'employee',
                fn ($q) => $q
                    ->where('department_id', $workTask->department_id)
                    ->where('is_active', true)
            )
            ->get()
            ->filter(
                fn (User $user): bool =>
                    $user->hasPermission('worklogs.view')
                    || $user->hasPermission('worklogs.manage')
            );

        foreach ($recipients as $user) {
            $mailerName = MailerResolver::resolveMailerName($user->email);
            $from       = MailerResolver::fromAddress($mailerName);
            $notification = new WorkTaskAssignedNotification(
                $workTask,
                $mailerName,
                $from
            );

            NotificationFacade::sendNow(
                $user,
                $notification,
                ['database']
            );

            if (filled($user->email)) {
                $user->notify($notification);
            }
        }
    }
}
