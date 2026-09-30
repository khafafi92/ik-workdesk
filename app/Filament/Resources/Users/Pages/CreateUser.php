<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Employee;
use App\Models\Role;
use App\Services\UserAccessHierarchyService;
use App\Services\UserAdditionalAccessService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected ?int $selectedEmployeeId = null;

    protected string $selectedAccessLevel = UserAccessHierarchyService::REQUESTER;

    protected array $selectedAdditionalAccess = [];

    protected function mutateFormDataBeforeCreate(
        array $data
    ): array {
        $actor = auth()->user();

        abort_unless(
            $actor
                && $actor->hasPermission('users.manage'),
            403,
            'Anda tidak memiliki izin membuat user.'
        );

        $this->selectedEmployeeId =
            isset($data['employee_id'])
                ? (int) $data['employee_id']
                : null;

        unset($data['employee_id']);

        $this->selectedAccessLevel = (string) (
            $data['access_level'] ?? UserAccessHierarchyService::REQUESTER
        );

        if (! app(UserAccessHierarchyService::class)->canAssign($actor, $this->selectedAccessLevel)) {
            throw ValidationException::withMessages([
                'access_level' => 'Level akses tidak tersedia atau tidak dapat Anda berikan.',
            ]);
        }

        $this->selectedAdditionalAccess = app(UserAdditionalAccessService::class)
            ->validateForLevel(
                $this->selectedAccessLevel,
                (array) ($data['additional_access'] ?? [])
            );
        unset($data['additional_access']);

        /*
        |--------------------------------------------------------------------------
        | Cegah privilege escalation
        |--------------------------------------------------------------------------
        */

        if ($actor->is_admin !== true) {
            $data['is_admin'] = false;
        } else {
            $data['is_admin'] =
                (bool) ($data['is_admin'] ?? false);
        }

        if (
            $data['is_admin'] === true
            && ! UserResource::canBeSuperAdministrator()
        ) {
            throw ValidationException::withMessages([
                'is_admin' => 'Only one Super Administrator is allowed.',
            ]);
        }

        if ($data['is_admin'] === true) {
            $this->selectedAccessLevel = 'system-admin';
            $data['access_level'] = 'system-admin';
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $actor = auth()->user();

        app(UserAccessHierarchyService::class)->syncPrimaryRole(
            $this->record,
            $this->selectedAccessLevel
        );
        app(UserAdditionalAccessService::class)->sync(
            $this->record,
            $this->selectedAdditionalAccess
        );

        /*
        |--------------------------------------------------------------------------
        | Pertahanan backend
        |--------------------------------------------------------------------------
        |
        | Walaupun request dimanipulasi, User Manager biasa tidak dapat
        | membuat Super Admin atau memberikan system-admin.
        |
        */

        if ($actor?->is_admin !== true) {
            $this->record
                ->forceFill([
                    'is_admin' => false,
                ])
                ->saveQuietly();

            $systemAdminRoleId = Role::query()
                ->where('code', 'system-admin')
                ->value('id');

            if ($systemAdminRoleId) {
                $this->record
                    ->roles()
                    ->detach($systemAdminRoleId);
            }
        }

        if (! $this->selectedEmployeeId) {
            return;
        }

        Employee::query()
            ->whereKey($this->selectedEmployeeId)
            ->whereNull('user_id')
            ->update([
                'user_id' => $this->record->id,
            ]);
    }
}
