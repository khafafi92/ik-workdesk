<?php

namespace App\Services;

use App\Models\AtkRequest;
use App\Models\AtkRequestItem;
use App\Models\User;
use App\Notifications\AtkRequestItemIssuedNotification;
use App\Notifications\AtkRequestSubmittedNotification;

class AtkRequestNotificationService
{
    public function notifyGaOfSubmittedRequest(AtkRequest $request): void
    {
        User::query()
            ->with([
                'employee.department',
                'roles.permissions',
                'directPermissions',
            ])
            ->get()
            ->filter(fn (User $user): bool => $this->isGaAtkHandler($user))
            ->each(fn (User $user) => $user->notify(
                new AtkRequestSubmittedNotification($request)
            ));
    }

    public function notifyRequesterOfIssuedItem(AtkRequestItem $requestItem, float $quantity): void
    {
        $requestItem->loadMissing([
            'item',
            'request.requester',
        ]);

        $requestItem->request?->requester?->notify(
            new AtkRequestItemIssuedNotification($requestItem, $quantity)
        );
    }

    private function isGaAtkHandler(User $user): bool
    {
        $department = $user->employee?->department;
        $departmentCode = strtoupper(trim((string) $department?->code));
        $departmentName = strtolower(trim((string) $department?->name));

        $isGa = $departmentCode === 'GA'
            || str_contains($departmentName, 'general affair')
            || $user->hasRole('general-affairs');

        return $isGa
            && $user->isActiveForAccess()
            && $user->hasPermission('atk.manage');
    }
}
