<?php

namespace App\Observers;

use App\Models\User;
use App\Models\WorkTask;
use App\Notifications\LegalTaskApprovalRequestedNotification;
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
        if ($workTask->isAwaitingLegalApproval()) {
            $this->notifyLegalApprovers($workTask);

            return;
        }

        $this->notifyDepartment($workTask);
    }

    private function notifyLegalApprovers(WorkTask $workTask): void
    {
        $recipients = User::query()
            ->with(['employee', 'roles.permissions', 'directPermissions'])
            ->where(function ($query): void {
                $query
                    ->where('is_admin', true)
                    ->orWhereHas(
                        'roles',
                        fn ($roleQuery) => $roleQuery
                            ->where('is_active', true)
                            ->where('code', 'system-admin')
                    )
                    ->orWhereHas(
                        'roles',
                        fn ($roleQuery) => $roleQuery
                            ->where('is_active', true)
                            ->whereHas(
                                'permissions',
                                fn ($permissionQuery) => $permissionQuery
                                    ->where('is_active', true)
                                    ->where('code', 'legal-tasks.approve')
                            )
                    )
                    ->orWhereHas(
                        'directPermissions',
                        fn ($permissionQuery) => $permissionQuery
                            ->where('is_active', true)
                            ->where('code', 'legal-tasks.approve')
                    );
            })
            ->get()
            ->filter(
                fn (User $user): bool => $user->isActiveForAccess()
                    && $user->hasPermission('legal-tasks.approve')
            );

        foreach ($recipients as $user) {
            $mailerName = MailerResolver::resolveMailerName($user->email);
            $from = MailerResolver::fromAddress($mailerName);
            $notification = new LegalTaskApprovalRequestedNotification(
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

    public function updated(WorkTask $workTask): void
    {
        if (
            $workTask->wasChanged('approval_status')
            && $workTask->approval_status === 'approved'
        ) {
            $this->notifyDepartment($workTask);
        }
    }

    private function notifyDepartment(WorkTask $workTask): void
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
                fn (User $user): bool => $user->hasPermission('worklogs.view')
                    || $user->hasPermission('worklogs.manage')
            );

        foreach ($recipients as $user) {
            $mailerName = MailerResolver::resolveMailerName($user->email);
            $from = MailerResolver::fromAddress($mailerName);
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
