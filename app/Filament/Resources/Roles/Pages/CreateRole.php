<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(
        array $data
    ): array {
        $actor = auth()->user();

        abort_unless(
            $actor
                && (
                    $actor->is_admin === true
                    || $actor->hasPermission(
                        'roles.manage'
                    )
                ),
            403,
            'You don\'t have a license to make roles.'
        );

        /*
         * system-admin adalah kode khusus dari sistem.
         * Tidak boleh dibuat ulang melalui panel.
         */
        if (
            ($data['code'] ?? null)
                === 'system-admin'
        ) {
            throw ValidationException::withMessages([
                'code' => 'System-admin code protected and unusable.',
            ]);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $actor = auth()->user();

        if ($actor?->is_admin === true) {
            return;
        }

        /*
         * Backend guard:
         * hapus permission yang tidak dimiliki pembuat role.
         */
        $allowedPermissionIds = $actor
            ?->roles()
            ->with('permissions:id')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('id')
            ->unique();

        $assignedPermissionIds = $this->record
            ->permissions()
            ->pluck('permissions.id');

        $forbiddenPermissionIds =
            $assignedPermissionIds->diff(
                $allowedPermissionIds
            );

        if ($forbiddenPermissionIds->isNotEmpty()) {
            $this->record
                ->permissions()
                ->detach(
                    $forbiddenPermissionIds->all()
                );
        }
    }
}
