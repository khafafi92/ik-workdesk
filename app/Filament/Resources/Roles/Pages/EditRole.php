<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeSave(
        array $data
    ): array {
        abort_unless(
            RoleResource::canEdit($this->record),
            403,
            'Anda tidak memiliki izin mengubah role ini.'
        );

        /*
         * Kode role system-admin tidak pernah boleh berubah.
         */
        if ($this->record->code === 'system-admin') {
            unset($data['code']);

            $data['is_active'] = true;
        }

        /*
         * Role biasa tidak boleh diubah menjadi system-admin.
         */
        if (
            $this->record->code !== 'system-admin'
            && ($data['code'] ?? null)
                === 'system-admin'
        ) {
            throw ValidationException::withMessages([
                'code' =>
                    'Kode system-admin dilindungi dan tidak dapat digunakan.',
            ]);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $actor = auth()->user();

        if ($actor?->is_admin === true) {
            return;
        }

        /*
         * Backend guard:
         * buang permission hasil manipulasi request.
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

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(
                    fn (): bool =>
                        RoleResource::canDelete(
                            $this->record
                        )
                )
                ->before(function (): void {
                    abort_unless(
                        RoleResource::canDelete(
                            $this->record
                        ),
                        403,
                        'Role ini tidak dapat dihapus.'
                    );
                }),
        ];
    }
}
