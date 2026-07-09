<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Employee;
use App\Models\Role;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected ?int $selectedEmployeeId = null;

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

        return $data;
    }

    protected function afterCreate(): void
    {
        $actor = auth()->user();

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
